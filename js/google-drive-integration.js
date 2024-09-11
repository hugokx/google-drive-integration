// File: wp-content/plugins/google-drive-integration/js/google-drive-integration.js

let gapiLoaded = false;
let gapiInitialized = false;

function loadGapiAndInit() {
    if (typeof gapi === 'undefined') {
        // Load the Google API script if it's not already loaded
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
    if (!gapiLoaded) return;
    
    gapi.load('client', () => {
        gapi.client.init({
            apiKey: googleDriveIntegration.apiKey,
            clientId: googleDriveIntegration.clientId,
            discoveryDocs: ["https://www.googleapis.com/discovery/v1/apis/drive/v3/rest"],
            scope: 'https://www.googleapis.com/auth/drive.readonly'
        }).then(() => {
            gapiInitialized = true;
            console.log('Google Drive API initialized');
            // Dispatch an event to notify that GAPI is ready
            document.dispatchEvent(new Event('gapi-loaded'));
        }).catch(error => {
            console.error('Error initializing Google Drive API:', error);
        });
    });
}

function listFolderContents(folderId) {
    return new Promise((resolve, reject) => {
        if (!gapiInitialized) {
            reject('Google API not initialized');
            return;
        }
        
        gapi.client.drive.files.list({
            q: `'${folderId}' in parents`,
            fields: 'files(id, name, mimeType, webContentLink)'
        }).then(response => {
            resolve(response.result.files);
        }).catch(reject);
    });
}

// Start loading GAPI when the script runs
loadGapiAndInit();

// Expose the listFolderContents function globally
window.listFolderContents = listFolderContents;