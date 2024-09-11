let tokenClient;
let gapiInited = false;
let gisInited = false;

// Initialize GAPI client
function initializeGapiClient() {
    console.log('Initializing GAPI Client...'); // Log when the function is called
    gapi.client.init({
        discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/drive/v3/rest'],
    }).then(() => {
        console.log('GAPI Client Initialized.');
        gapiInited = true;
        maybeEnableButtons(); // Check if both GAPI and GIS are initialized
    }).catch(error => {
        console.error('Error initializing GAPI client:', error);
    });
}

// Google Identity Services loaded
function gisLoaded() {
    console.log('Google Identity Services Loaded...'); // Log when the function is called
    tokenClient = google.accounts.oauth2.initTokenClient({
        client_id: googleDriveIntegrationAdmin.clientId, // Client ID passed from PHP
        scope: 'https://www.googleapis.com/auth/drive.readonly',
        callback: '', // Set later in handleAuthClick
    });
    gisInited = true;
    maybeEnableButtons(); // Check if both GAPI and GIS are initialized
}

// Enable the buttons once GAPI and GIS are both initialized
function maybeEnableButtons() {
    if (gapiInited && gisInited) {
        console.log('Enabling the authorize button...'); // Log when the button is enabled
        document.getElementById('authorize_button').style.visibility = 'visible';
    }
}

// Handle the authorization click
function handleAuthClick() {
    console.log('Handling authorization click...'); // Log when the button is clicked
    tokenClient.callback = async (resp) => {
        if (resp.error !== undefined) {
            console.error('Auth error:', resp.error);
            return;
        }

        // Once we have the token, we send it to the backend
        console.log('Token received:', resp.access_token);
        // Set the access token for GAPI client
        gapi.client.setToken(resp);
        console.log('Google Drive authentication successful!');
        document.getElementById('content').innerText = 'Authentication successful! You can now use Google Drive API.';
    };

    if (gapi.client.getToken() === null) {
        tokenClient.requestAccessToken({prompt: 'consent'});
    } else {
        tokenClient.requestAccessToken({prompt: ''});
    }

    // If using a custom popup, make sure to add "noopener"
    window.open(auth_url, '_blank', 'noopener');    
}

/* Function to list folder contents from Google Drive
function listFolderContents(folderId) {
    console.log('Listing folder contents for folder ID:', folderId); // Log when the function is called
    return new Promise((resolve, reject) => {
        if (!gapiInited) {
            console.error('Google API not initialized');
            reject('Google API not initialized');
            return;
        }

        gapi.client.drive.files.list({
            q: `'${folderId}' in parents`,
            fields: 'files(id, name, mimeType, webViewLink)',
        }).then((response) => {
            console.log('Files in folder:', response.result.files);
            resolve(response.result.files);
        }).catch((error) => {
            console.error('Error fetching folder contents:', error);
            reject(error);
        });
    });
}*/

// Wait until both scripts are loaded before calling the initialize functions
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM content loaded.');

    if (typeof gapi !== 'undefined') {
        console.log('Google API script loaded.');
        gapi.load('client', initializeGapiClient); // Load GAPI client when script is ready
    } else {
        console.error('Google API script not found.');
    }

    if (typeof google !== 'undefined' && typeof google.accounts !== 'undefined') {
        console.log('Google Identity Services script loaded.');
        gisLoaded(); // Initialize GIS when script is ready
    } else {
        console.error('Google Identity Services script not found.');
    }
});

// Expose functions globally so they can be called from outside the script
window.handleAuthClick = handleAuthClick;
//window.listFolderContents = listFolderContents;
window.initializeGapiClient = initializeGapiClient; // Make sure initializeGapiClient is global
window.gisLoaded = gisLoaded; // Make sure gisLoaded is global
