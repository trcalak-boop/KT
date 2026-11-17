ObjC.import('PDFKit'); ObjC.import('AppKit');
function run(argv) {
  var doc = $.PDFDocument.alloc.initWithURL($.NSURL.fileURLWithPath(argv[0]));
  var n = doc.pageCount, od = parseInt(argv[2] || '1'), do_ = parseInt(argv[3] || n);
  for (var i = od - 1; i < Math.min(n, do_); i++) {
    var img = doc.pageAtIndex(i).thumbnailOfSizeForBox($.NSMakeSize(700, 990), 0);
    var rep = $.NSBitmapImageRep.imageRepWithData(img.TIFFRepresentation);
    var data = rep.representationUsingTypeProperties(3, $({}));
    data.writeToFileAtomically(argv[1] + '/s' + ('00' + (i + 1)).slice(-3) + '.jpg', true);
  }
  return 'stran: ' + n;
}
