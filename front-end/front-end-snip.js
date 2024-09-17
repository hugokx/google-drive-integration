jQuery(document).ready(function($) {
    // Ensure the banner carousel element exists before proceeding
    if ($('#banner-carousel').length > 0) {
        console.log('Banner carousel exists, initializing loadBanners...');

        // Function to load banners from the /wp-content/uploads/EnCours/ folder
        function loadBanners(folderPath) {
            console.log('loadBanners called with folderPath:', folderPath);

            // Use AJAX to retrieve the list of images from the server
            $.ajax({
                url: '<?php echo $ajax_url; ?>', // Use your own AJAX URL if needed
                method: 'POST',
                data: {
                    action: 'get_local_banners', // Custom PHP function to fetch local image files
                    folderPath: folderPath // The folder path passed from the function
                },
                success: function(response) {
                    console.log('AJAX success response:', response);

                    if (response.success) {
                        const bannerData = response.data;
                        console.log('Banner data received:', bannerData);

                        if (bannerData.length === 0) {
                            console.error('No banners found in the folder.');
                            return;
                        }

                        // Build the carousel
                        bannerData.forEach(banner => {
                            const categoryPath = banner.category_path; // Use the full category path for URL
                            const imgSrc = banner.url; // Image URL for the local folder

                            // Build the product category URL using the full category path
                            const categoryUrl = `/product-category/${categoryPath}`;

                            $('#banner-carousel').append(`
                                <div class="item">
                                    <a href="${categoryUrl}">
                                        <img src="${imgSrc}" alt="Category ${categoryPath}"/>
                                    </a>
                                </div>
                            `);
                        });

                        // Initialize the carousel
                        $('#banner-carousel').owlCarousel({
                            loop: true,
                            margin: 20,
                            nav: false,
                            dots: true,
                            autoplay: true,
                            autoplayTimeout: 8000,
                            responsive: {
                                0: {
                                    items: 1
                                },
                                600: {
                                    items: 1
                                },
                                1000: {
                                    items: 1
                                }
                            }
                        });
                    } else {
                        console.error('Failed to retrieve banner data:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', {
                        jqXHR: jqXHR,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                }
            });
        }

        // Load banners for the local folder 'EnCours'
        loadBanners('/wp-content/uploads/EnCours/');
    } else {
        console.log('Banner carousel not found on this page.');
    }
});
