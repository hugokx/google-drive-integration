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
    if (!gapiLoaded) {
        console.log('Waiting for GAPI to load...');
        setTimeout(initGoogleDriveAPI, 100);
        return;
    }
    
    gapi.load('client:auth2', () => {
        gapi.client.init({
            clientId: googleDriveIntegration.clientId,
            scope: 'https://www.googleapis.com/auth/drive.readonly'
        }).then(() => {
            gapiInitialized = true;
            console.log('Google Drive API initialized');
            // Check if the user is signed in
            if (!gapi.auth2.getAuthInstance().isSignedIn.get()) {
                // If not signed in, trigger the sign-in flow
                return gapi.auth2.getAuthInstance().signIn();
            }
        }).then(() => {
            // Dispatch an event to notify that GAPI is ready and authenticated
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