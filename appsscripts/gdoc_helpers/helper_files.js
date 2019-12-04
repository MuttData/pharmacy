function removeFiles(opts) {
  var folder   = DriveApp.getFoldersByName(opts.folder).next()
  var iterator = folder.searchFiles('title contains "'+opts.file+'"')
  var res      = []

  while (iterator.hasNext()) {
    var file = iterator.next()
    try {
      file.setTrashed(true) //Prevent printing an old list that Cindy pended and shipped on her own
      res.push(['success', file.getUrl(), file.getName(), file.getOwner()])
    } catch (e) {
      res.push(['error', file.getUrl(), file.getName(), file.getOwner()]) //e.g., Error: "Access denied: DriveApp."
    }
  }

  infoEmail('removeFiles', opts, res)
  return ['removeFiles', opts, res]
}

function testWatch() {
  var folder = DriveApp.getFoldersByName('OLD').next()
  var query  = 'modifiedDate > "2019-11-19T16:07:49.089Z"'
  var iterator = folder.searchFiles(query)

  Logger.log(['testWatch', query, iterator.hasNext() ? iterator.next().getUrl() : 'No Files Modified'])
}

function watchFiles(opts) {

  var today     = new Date();
  var minutes   = opts.minutes || 10
  var startTime = new Date(today.getTime() - minutes * 60 * 1000);
  var tooRecent = new Date(today.getTime() - 3 * 60 * 1000); //Don't call if we are still making edits

  var files    = []
  var folder   = DriveApp.getFoldersByName(opts.folder).next()
  var iterator = folder.searchFiles('modifiedDate > "' + startTime.toISOString() + '" AND modifiedDate < "' + tooRecent.toISOString() + '"')

  while (iterator.hasNext()) {

    var next = iterator.next()
    var file = {
      name:next.getName(),
      id:next.getId(),
      url:next.getUrl(),
      date_modified:next.getLastUpdated(),
      date_created:next.getDateCreated()
    }

    //If don't want watch to keep returning the same file with the same change multiple times
    var last_watched = file.name.split(' Modified:').pop()

    file.first = ! last_watched || last_watched >= file.date_modified
    file.isNew = (file.date_modified - file.date_created) < 1 * 60 * 1000 //1 minute

    if ( ! file.first || ( ! opts.includeNew && file.isNew)) continue;

    //This makes last_watched logic work
    file.setName(file.name+' Modified:'+file.date_modified)

    //getBody does not have headers or footers
    var doc = DocumentApp.openById(next.getId())
    var documentElement = doc.getBody().getParent()
    var numChildren = documentElement.getNumChildren()

    for (var i = 0; i<numChildren; i++) {
      var child = documentElement.getChild(i)
      file['part'+i] = child.getText()
    }

    files.push(file)
  }

  infoEmail('watchFiles', folder, files)
  return files
}

//Drive (not DriveApp) must be turned on under Resources -> Advanced Google Services -> Drive
//https://stackoverflow.com/questions/40476324/how-to-publish-to-the-web-a-spreadsheet-using-drive-api-and-gas
function publishFile(opts){

  var folder = DriveApp.getFoldersByName(opts.folder).next()
  var file   = folder.searchFiles('title contains "'+opts.file+'"').next()
  var fileId = file.getId()
  //Side effect of this is that this account can no longer delete/trash/remove this file since must be done by owner
  file.setOwner('admin@sirum.org') //support@goodpill.org can only publish files that require sirum sign in

  var revisions = Drive.Revisions.list(fileId);
  var items = revisions.items;
  var revisionId = items[items.length-1].id;
  var resource = Drive.Revisions.get(fileId, revisionId);

  resource.published = true;
  resource.publishAuto = true;
  resource.publishedOutsideDomain = true;
  resource = Drive.Revisions.update(resource, fileId, revisionId);

  return resource
}

function newSpreadsheet(opts) {

  var ss   = SpreadsheetApp.create(opts.file)
  var file = DriveApp.getFileById(ss.getId())

  if (opts.vals) {
    ss.getActiveSheet()
      .getRange(1, 1, opts.vals.length, opts.vals[0].length)
      .setValues(opts.vals)
      .setHorizontalAlignment('left')
      .setFontFamily('Roboto Mono')
  }

  var widths = opts.widths || {}
  for (var col in widths) {
    ss.setColumnWidth(col, widths[col]); //show the full id when it print
  }

  moveToFolder(file, opts.folder)
}

function moveToFolder(file, folder) {
  if ( ! folder ) return
  parentByFile(file).removeFile(file)
  folderByName(folder).addFile(file)
  return file
}

function folderByName(name) {
  return DriveApp.getFoldersByName(name).next()
}

function parentByFile(file) {

  try {
    return file.getParents().next()
  } catch(e) {
    return DriveApp.getRootFolder()
  }
}
