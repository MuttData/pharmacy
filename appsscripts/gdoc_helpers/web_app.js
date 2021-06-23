/**
 * Handle a get request if needed.
 * @param  {object} e The data from the request
 * @return {ContentService}   A formed JSON respons
 */
function doGet(e) {
    if (e.parameter.GD_KEY != 'Patients1st!') {
      return ContentService
        .createTextOutput("Access Denied")
        .setMimeType(ContentService.MimeType.JSON)
    }

    var response = fileDetails(e.parameter.fileId);

    return ContentService
      .createTextOutput(JSON.stringify(response))
      .setMimeType(ContentService.MimeType.JSON)

}

/**
 * Handle a post request
 * @param  {object} e The data from the request
 * @return {ContentService}   A formed JSON respons
 */
function doPost(e) {

    try {

        Log('doPost', e)

        if (e.parameter.GD_KEY != GD_KEY)
            return console.error('web_app post wrong password', e)

        if (!e.postData || !e.postData.contents)
            return console.error('web_app post not post data', e, e.postData,  e.postData && e.postData.contents)

        var response
        var contents = JSON.parse(e.postData.contents)

        if ( ~ contents.method.indexOf('v2')) {
            Log('doPost v2 route', contents, e)
            response = v2_routes(contents);
        } else {
            Log('doPost v1 route', contents, e)
            response = v1_routes(contents);
        }

        return ContentService
            .createTextOutput(JSON.stringify(response || 'gdoc_helper had not return value'))
            .setMimeType(ContentService.MimeType.JSON)

    } catch (err) {

        console.error('web_app post error thrown', err, e)

        return ContentService
            .createTextOutput(JSON.stringify([err, err.stack]))
            .setMimeType(ContentService.MimeType.JSON)
    }
}

/**
 * handle any older v1 routes
 * @param  {object} contents All data that was sent to the request
 * @return {object}          Data to represent the results
 */
function v1_routes(contents) {
    switch (contents.method) {
        case 'removeFiles':
            return removeFiles(contents);
        case 'watchFiles':
            return watchFiles(contents);
        case 'publishFile':
            return publishFile(contents);
        case 'newSpreadsheet':
            return newSpreadsheet(contents);
        case 'createCalendarEvent':
            return createCalendarEvent(contents);
        case 'removeCalendarEvents':
            return removeCalendarEvents(contents);
        case 'searchCalendarEvents':
            return searchCalendarEvents(contents);
        case 'modifyCalendarEvents':
            return modifyCalendarEvents(contents);
        case 'shortLinks':
            return shortLinks(contents);
        case 'moveFile':
            return moveFile(contents);
        default:
            console.log('Could not find a match in v1 route', contents);
    }
}

/**
 * Handle any routes that have a v2/ in them
 * @param  {object} contents All data that was sent to the request
 * @return {object}          Date to represent the results
 */
function v2_routes(contents) {
    switch (contents.method) {
        case 'v2/removeFile':
          return removeFile_v2(contents.fileId);
        case 'v2/moveFile':
          return moveFile_v2(contents.fileId, contents.folderId);
        case 'v2/publishFile':
          return publishFile_v2(contents.fileId);
        case 'v2/fileDetails':
          return moveFile(contents);
        case 'v2/createEvent':
          return createEvent_v2(contents.hours, contents.start, contents.cal_id, contents.title, contents.description);
        case 'v2/removeEvents':
          return removeEvents_v2(contents.cal_id, contents.ids);
        case 'v2/searchAndDeleteEvents':
          return searchAndDeleteEvents_v2(contents.cal_id, contents.word_search, contents.hours, contents.regex_search);
        default:
          console.log('Could not find a match in v2 route', contents);
          return {
            "results":"error",
            "error":"could not find " + contents.method
          }
    }
}
