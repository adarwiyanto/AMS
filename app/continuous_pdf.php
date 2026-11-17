<?php
/** Single-page PDF renderer tanpa Composer. Ukuran halaman menyesuaikan tinggi konten. */
class AMSContinuousPDF {
  private array $operations = [];
  private array $images = [];
  private float $y = 30;
  private float $width = 595.28;
  private float $margin = 42;
  private float $fontSize = 11;
  private function txt(string $str): string {
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str);
    $converted = $converted === false ? $str : $converted;
    return str_replace(["\\",'(',')',"\r","\n"], ['\\\\','\\(','\\)',' ',' '], $converted);
  }
  private function estimate(string $str, float $size): float {
    return strlen((string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str)) * $size * .50;
  }
  private function wrap(string $line, float $size, float $max): array {
    $words = preg_split('/\s+/u', trim($line)) ?: [];
    $lines=[]; $current='';
    foreach($words as $word) {
      if($word==='') continue;
      $test= $current===''?$word:$current.' '.$word;
      if($this->estimate($test,$size)<=$max) { $current=$test;continue; }
      if($current!=='') {$lines[]=$current;$current='';}
      while(count(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY))>1 && $this->estimate($word,$size)>$max) {
        $piece='';
        foreach(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY) as $char) {
          if($piece!=='' && $this->estimate($piece.$char,$size)>$max) break;
          $piece.=$char;
        }
        if($piece==='') break;
        $lines[]=$piece; $word=implode('',array_slice(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY),count(preg_split('//u',$piece,-1,PREG_SPLIT_NO_EMPTY))));
      }
      $current=$word;
    }
    if($current!=='') $lines[]=$current;
    return $lines ?: [''];
  }
  public function text(string $value, int $size=11, bool $bold=false, float $gap=3): void {
    $font=$bold?'F2':'F1';
    foreach (preg_split('/\R/u',$value) ?: [] as $paragraph) {
      foreach($this->wrap($paragraph,(float)$size,$this->width-2*$this->margin) as $line) {
        // Koordinat PDF dari bawah, dihitung setelah tinggi halaman final.
        $this->operations[]=['text',$this->margin,$this->y,$size,$font,$this->txt($line)];
        $this->y += $size*1.42;
      }
    }
    $this->y += $gap;
  }
  public function heading(string $value): void { $this->y+=9;$this->text($value,11,true,3); }
  public function rule(): void { $this->operations[]=['line',$this->margin,$this->y,$this->width-$this->margin];$this->y+=12; }
  public function image(string $path, float $maxWidth=400, float $maxHeight=230): void {
    if(!is_file($path))return;
    $info=@getimagesize($path);if(!$info)return;
    $temp=null;
    if($info[2]!==IMAGETYPE_JPEG) {
      if(!function_exists('imagecreatefromstring'))return;
      $source=@imagecreatefromstring((string)file_get_contents($path));if(!$source)return;
      $temp=imagecreatetruecolor(imagesx($source),imagesy($source));
      imagefill($temp,0,0,imagecolorallocate($temp,255,255,255));imagecopy($temp,$source,0,0,0,0,imagesx($source),imagesy($source));
      imagedestroy($source);ob_start();imagejpeg($temp,null,82);$data=ob_get_clean();imagedestroy($temp);
    } else $data=@file_get_contents($path);
    if(!$data)return;
    $factor=min($maxWidth/$info[0],$maxHeight/$info[1],1);
    $w=$info[0]*$factor;$h=$info[1]*$factor;
    $key='Im'.(count($this->images)+1);
    $this->images[$key]=['data'=>$data,'width'=>$info[0],'height'=>$info[1]];
    $this->operations[]=['image',$key,$this->margin,$this->y,$w,$h];$this->y+=$h+10;
  }
  public function output(string $filename): void {
    $height=max(841.89,$this->y+42);
    // PDF readers may cap user-space pages (14400pt); truncate never silently.
    if($height>14000) throw new RuntimeException('Dokumen melebihi batas tinggi satu halaman PDF (sekitar 4,9 meter). Kurangi jumlah gambar USG.');
    $stream='';
    foreach($this->operations as $op) {
      if($op[0]==='text') {
        [,$x,$y,$size,$font,$str]=$op;
        $stream.=sprintf("BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",$font,$size,$x,$height-$y-$size,$str);
      } elseif($op[0]==='line') {
        $stream.=sprintf("0.75 G 0.7 w %.2f %.2f m %.2f %.2f l S\n",$op[1],$height-$op[2],$op[3],$height-$op[2]);
      } else {
        [,$key,$x,$y,$w,$h]=$op;
        $stream.=sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n",$w,$h,$x,$height-$y-$h,$key);
      }
    }
    $objects=[];
    $add=function(string $value) use (&$objects):int { $objects[]=$value;return count($objects); };
    $catalog=$add('');$pages=$add('');$page=$add('');
    $content=$add('<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream");
    $regular=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $bold=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
    $refs=[];
    foreach($this->images as $key=>$im) {
      $refs[]='/'.$key.' '.$add('<< /Type /XObject /Subtype /Image /Width '.$im['width'].' /Height '.$im['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($im['data'])." >>\nstream\n".$im['data']."\nendstream").' 0 R';
    }
    $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';
    $objects[$pages-1]='<< /Type /Pages /Kids ['.$page.' 0 R] /Count 1 >>';
    $objects[$page-1]=sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> /XObject << %s >> >> /Contents %d 0 R >>',$pages,$this->width,$height,$regular,$bold,implode(' ',$refs),$content);
    $bytes="%PDF-1.4\n";$offset=[0];
    foreach($objects as $i=>$obj){$offset[]=strlen($bytes);$bytes.=($i+1)." 0 obj\n".$obj."\nendobj\n";}
    $xref=strlen($bytes);$bytes.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for($i=1;$i<count($offset);$i++)$bytes.=sprintf('%010d 00000 n ', $offset[$i])."\n";
    $bytes.="trailer\n<< /Size ".(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF";
    header('Content-Type: application/pdf'); header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="'.preg_replace('/[^a-zA-Z0-9_-]/','_', $filename).'"');
    header('Content-Length: '.strlen($bytes));echo $bytes;
  }
}
