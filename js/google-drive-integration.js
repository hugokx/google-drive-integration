// File: js/google-drive-integration.js

let gapi;

function initGoogleDriveAPI() {
    gapi.load('client:auth2', () => {
        gapi.client.init({
            clientId: googleDriveIntegration.clientId,
            scope: 'https://www.googleapis.com/auth/drive.readonly'
        }).then(() => {
            // API is initialized and ready
            console.log('Google Drive API initialized');
        });
    });
}

function listFolderContents(folderId) {
    return gapi.client.drive.files.list({
        q: `'${folderId}' in parents`,
        fields: 'files(id, name, mimeType, webViewLink)'
    }).then(response => {
        return response.result.files;
    });
}

// Function to be called from PHP
function getDriveFolderContents(folderId) {
    return new Promise((resolve, reject) => {
        gapi.auth2.getAuthInstance().signIn().then(() => {
            listFolderContents(folderId).then(files => {
                resolve(files);
            }).catch(error => {
                reject(error);
            });
        }).catch(error => {
            reject(error);
        });
    });
}

// Initialize the API when the script loads
initGoogleDriveAPI();