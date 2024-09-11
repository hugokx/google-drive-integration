// File: wp-content/plugins/google-drive-integration/js/google-drive-integration.js

let gapiLoaded = false;
let gapiInitialized = false;

function loadGapiAndInit() {
    if (typeof gapi === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://apis.google.com/js/api.js';
        script.onload = function() {
            gapiLoaded = true;
            initGoogleDriveAPI();
        };
        document.head.appendChild(script);
    } else {
        gapiLoaded = true;
        initGoogleDriveAPI();
    }
}

function initGoogleDriveAPI() {
    gapi.load('client', () => {
        gapi.client.init({
            clientId: googleDriveIntegration.clientId,
            scope: 'https://www.googleapis.com/auth/drive.readonly',
            discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/drive/v3/rest']
        }).then(() => {
            // Set the access token obtained from the server
            if (googleDriveIntegration.accessToken) {
                gapi.client.setToken(googleDriveIntegration.accessToken);
            }
            gapiLoaded = true;
            console.log('Google Drive API initialized');
            document.dispatchEvent(new Event('gapi-loaded'));
        }).catch(error => {
            console.error('Error initializing Google Drive API:', error);
        });
    });
}

function listFolderContents(folderId) {
    return new Promise((resolve, reject) => {
        if (!gapiLoaded) {
            reject('Google API not initialized');
            return;
        }
        
        gapi.client.drive.files.list({
            q: `'${folderId}' in parents`,
            fields: 'files(id, name, mimeType, webViewLink)'
        }).then(response => {
            resolve(response.result.files);
        }).catch(reject);
    });
}

// Start loading GAPI when the script runs
loadGapiAndInit();

// Expose the listFolderContents function globally
window.listFolderContents = listFolderContents;