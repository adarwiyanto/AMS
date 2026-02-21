window.config = {
  routerBasename: '/ohif',
  servers: {
    dicomWeb: [
      {
        name: 'AMS PACS',
        wadoUriRoot: window.location.origin + '/pacs/wado',
        qidoRoot: window.location.origin + '/pacs/dicomweb',
        wadoRoot: window.location.origin + '/pacs/dicomweb',
        qidoSupportsIncludeField: true,
        imageRendering: 'wadouri',
        thumbnailRendering: 'wadouri',
      },
    ],
  },
};
