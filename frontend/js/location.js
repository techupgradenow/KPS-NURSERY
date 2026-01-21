/**
 * Location Management JavaScript
 * KPS Nursery App - Location selector functionality with Autocomplete
 */

// Comprehensive location database for India
const locationDatabase = [
    // Tamil Nadu - Chennai
    { id: 1, name: 'Kovil Street', area: 'Mylapore', city: 'Chennai', state: 'Tamil Nadu', pincode: '600004' },
    { id: 2, name: 'Kovilpathagai', area: 'Koyambedu', city: 'Chennai', state: 'Tamil Nadu', pincode: '600107' },
    { id: 3, name: 'Kovil Medu', area: 'Ambattur', city: 'Chennai', state: 'Tamil Nadu', pincode: '600053' },
    { id: 4, name: 'Kovil Nagar', area: 'Perungudi', city: 'Chennai', state: 'Tamil Nadu', pincode: '600096' },
    { id: 5, name: 'T Nagar', area: 'Thyagaraya Nagar', city: 'Chennai', state: 'Tamil Nadu', pincode: '600017' },
    { id: 6, name: 'Anna Nagar', area: 'Anna Nagar West', city: 'Chennai', state: 'Tamil Nadu', pincode: '600040' },
    { id: 7, name: 'Velachery', area: 'Velachery Main Road', city: 'Chennai', state: 'Tamil Nadu', pincode: '600042' },
    { id: 8, name: 'Adyar', area: 'Adyar', city: 'Chennai', state: 'Tamil Nadu', pincode: '600020' },
    { id: 9, name: 'Tambaram', area: 'Tambaram East', city: 'Chennai', state: 'Tamil Nadu', pincode: '600059' },
    { id: 10, name: 'Porur', area: 'Porur', city: 'Chennai', state: 'Tamil Nadu', pincode: '600116' },
    { id: 11, name: 'Guindy', area: 'Guindy Industrial Estate', city: 'Chennai', state: 'Tamil Nadu', pincode: '600032' },
    { id: 12, name: 'Nungambakkam', area: 'Nungambakkam High Road', city: 'Chennai', state: 'Tamil Nadu', pincode: '600034' },
    { id: 13, name: 'Egmore', area: 'Egmore', city: 'Chennai', state: 'Tamil Nadu', pincode: '600008' },
    { id: 14, name: 'Chromepet', area: 'Chromepet', city: 'Chennai', state: 'Tamil Nadu', pincode: '600044' },
    { id: 15, name: 'Sholinganallur', area: 'OMR', city: 'Chennai', state: 'Tamil Nadu', pincode: '600119' },

    // Tamil Nadu - Other Cities
    { id: 16, name: 'Kovil Patti', area: 'Kovilpatti Town', city: 'Thoothukudi', state: 'Tamil Nadu', pincode: '628501' },
    { id: 17, name: 'RS Puram', area: 'RS Puram', city: 'Coimbatore', state: 'Tamil Nadu', pincode: '641002' },
    { id: 18, name: 'Gandhipuram', area: 'Gandhipuram', city: 'Coimbatore', state: 'Tamil Nadu', pincode: '641012' },
    { id: 19, name: 'Peelamedu', area: 'Peelamedu', city: 'Coimbatore', state: 'Tamil Nadu', pincode: '641004' },
    { id: 20, name: 'Saibaba Colony', area: 'Saibaba Colony', city: 'Coimbatore', state: 'Tamil Nadu', pincode: '641011' },
    { id: 21, name: 'Srirangam', area: 'Srirangam', city: 'Trichy', state: 'Tamil Nadu', pincode: '620006' },
    { id: 22, name: 'Thillai Nagar', area: 'Thillai Nagar', city: 'Trichy', state: 'Tamil Nadu', pincode: '620018' },
    { id: 23, name: 'KK Nagar', area: 'KK Nagar', city: 'Madurai', state: 'Tamil Nadu', pincode: '625020' },
    { id: 24, name: 'Anna Nagar', area: 'Anna Nagar', city: 'Madurai', state: 'Tamil Nadu', pincode: '625020' },

    // Haryana - Gurgaon
    { id: 25, name: 'Sector 15', area: 'Sector 15', city: 'Gurgaon', state: 'Haryana', pincode: '122001' },
    { id: 26, name: 'Sector 29', area: 'Sector 29', city: 'Gurgaon', state: 'Haryana', pincode: '122001' },
    { id: 27, name: 'DLF Phase 1', area: 'DLF City Phase 1', city: 'Gurgaon', state: 'Haryana', pincode: '122002' },
    { id: 28, name: 'DLF Phase 2', area: 'DLF City Phase 2', city: 'Gurgaon', state: 'Haryana', pincode: '122002' },
    { id: 29, name: 'DLF Phase 3', area: 'DLF City Phase 3', city: 'Gurgaon', state: 'Haryana', pincode: '122002' },
    { id: 30, name: 'MG Road', area: 'MG Road', city: 'Gurgaon', state: 'Haryana', pincode: '122001' },
    { id: 31, name: 'Sohna Road', area: 'Sohna Road', city: 'Gurgaon', state: 'Haryana', pincode: '122001' },
    { id: 32, name: 'Golf Course Road', area: 'Golf Course Road', city: 'Gurgaon', state: 'Haryana', pincode: '122002' },
    { id: 33, name: 'Cyber City', area: 'DLF Cyber City', city: 'Gurgaon', state: 'Haryana', pincode: '122002' },
    { id: 34, name: 'Udyog Vihar', area: 'Udyog Vihar Phase 4', city: 'Gurgaon', state: 'Haryana', pincode: '122015' },

    // Delhi NCR
    { id: 35, name: 'Connaught Place', area: 'CP', city: 'New Delhi', state: 'Delhi', pincode: '110001' },
    { id: 36, name: 'Karol Bagh', area: 'Karol Bagh', city: 'New Delhi', state: 'Delhi', pincode: '110005' },
    { id: 37, name: 'Dwarka', area: 'Dwarka Sector 12', city: 'New Delhi', state: 'Delhi', pincode: '110075' },
    { id: 38, name: 'Rohini', area: 'Rohini Sector 7', city: 'New Delhi', state: 'Delhi', pincode: '110085' },
    { id: 39, name: 'Saket', area: 'Saket', city: 'New Delhi', state: 'Delhi', pincode: '110017' },
    { id: 40, name: 'Lajpat Nagar', area: 'Lajpat Nagar', city: 'New Delhi', state: 'Delhi', pincode: '110024' },
    { id: 41, name: 'Greater Kailash', area: 'GK 1', city: 'New Delhi', state: 'Delhi', pincode: '110048' },
    { id: 42, name: 'Noida Sector 18', area: 'Sector 18', city: 'Noida', state: 'Uttar Pradesh', pincode: '201301' },
    { id: 43, name: 'Noida Sector 62', area: 'Sector 62', city: 'Noida', state: 'Uttar Pradesh', pincode: '201309' },

    // Maharashtra - Mumbai
    { id: 44, name: 'Andheri West', area: 'Andheri West', city: 'Mumbai', state: 'Maharashtra', pincode: '400058' },
    { id: 45, name: 'Bandra West', area: 'Bandra West', city: 'Mumbai', state: 'Maharashtra', pincode: '400050' },
    { id: 46, name: 'Powai', area: 'Powai', city: 'Mumbai', state: 'Maharashtra', pincode: '400076' },
    { id: 47, name: 'Juhu', area: 'Juhu', city: 'Mumbai', state: 'Maharashtra', pincode: '400049' },
    { id: 48, name: 'Malad West', area: 'Malad West', city: 'Mumbai', state: 'Maharashtra', pincode: '400064' },
    { id: 49, name: 'Borivali West', area: 'Borivali West', city: 'Mumbai', state: 'Maharashtra', pincode: '400092' },
    { id: 50, name: 'Thane West', area: 'Thane West', city: 'Thane', state: 'Maharashtra', pincode: '400601' },

    // Karnataka - Bangalore
    { id: 51, name: 'Koramangala', area: 'Koramangala 4th Block', city: 'Bangalore', state: 'Karnataka', pincode: '560034' },
    { id: 52, name: 'Indiranagar', area: 'Indiranagar', city: 'Bangalore', state: 'Karnataka', pincode: '560038' },
    { id: 53, name: 'HSR Layout', area: 'HSR Layout Sector 1', city: 'Bangalore', state: 'Karnataka', pincode: '560102' },
    { id: 54, name: 'Whitefield', area: 'Whitefield', city: 'Bangalore', state: 'Karnataka', pincode: '560066' },
    { id: 55, name: 'Electronic City', area: 'Electronic City Phase 1', city: 'Bangalore', state: 'Karnataka', pincode: '560100' },
    { id: 56, name: 'Jayanagar', area: 'Jayanagar 4th Block', city: 'Bangalore', state: 'Karnataka', pincode: '560041' },
    { id: 57, name: 'Marathahalli', area: 'Marathahalli', city: 'Bangalore', state: 'Karnataka', pincode: '560037' },
    { id: 58, name: 'BTM Layout', area: 'BTM Layout 2nd Stage', city: 'Bangalore', state: 'Karnataka', pincode: '560076' },

    // Telangana - Hyderabad
    { id: 59, name: 'Banjara Hills', area: 'Banjara Hills', city: 'Hyderabad', state: 'Telangana', pincode: '500034' },
    { id: 60, name: 'Jubilee Hills', area: 'Jubilee Hills', city: 'Hyderabad', state: 'Telangana', pincode: '500033' },
    { id: 61, name: 'Hitech City', area: 'HITEC City', city: 'Hyderabad', state: 'Telangana', pincode: '500081' },
    { id: 62, name: 'Gachibowli', area: 'Gachibowli', city: 'Hyderabad', state: 'Telangana', pincode: '500032' },
    { id: 63, name: 'Madhapur', area: 'Madhapur', city: 'Hyderabad', state: 'Telangana', pincode: '500081' },
    { id: 64, name: 'Kondapur', area: 'Kondapur', city: 'Hyderabad', state: 'Telangana', pincode: '500084' },

    // West Bengal - Kolkata
    { id: 65, name: 'Salt Lake', area: 'Salt Lake City Sector 5', city: 'Kolkata', state: 'West Bengal', pincode: '700091' },
    { id: 66, name: 'Park Street', area: 'Park Street', city: 'Kolkata', state: 'West Bengal', pincode: '700016' },
    { id: 67, name: 'New Town', area: 'New Town Action Area 1', city: 'Kolkata', state: 'West Bengal', pincode: '700156' },

    // Gujarat
    { id: 68, name: 'SG Highway', area: 'SG Highway', city: 'Ahmedabad', state: 'Gujarat', pincode: '380054' },
    { id: 69, name: 'Prahlad Nagar', area: 'Prahlad Nagar', city: 'Ahmedabad', state: 'Gujarat', pincode: '380015' },
    { id: 70, name: 'Vastrapur', area: 'Vastrapur', city: 'Ahmedabad', state: 'Gujarat', pincode: '380015' },

    // Kerala
    { id: 71, name: 'MG Road', area: 'MG Road', city: 'Kochi', state: 'Kerala', pincode: '682016' },
    { id: 72, name: 'Kakkanad', area: 'Kakkanad', city: 'Kochi', state: 'Kerala', pincode: '682030' },
    { id: 73, name: 'Edappally', area: 'Edappally', city: 'Kochi', state: 'Kerala', pincode: '682024' },
    { id: 74, name: 'Kovil Thottam', area: 'Kovil Thottam', city: 'Thrissur', state: 'Kerala', pincode: '680001' },
    { id: 75, name: 'Kovilakam', area: 'Kovilakam Road', city: 'Trivandrum', state: 'Kerala', pincode: '695001' },

    // Rajasthan
    { id: 76, name: 'C Scheme', area: 'C Scheme', city: 'Jaipur', state: 'Rajasthan', pincode: '302001' },
    { id: 77, name: 'Malviya Nagar', area: 'Malviya Nagar', city: 'Jaipur', state: 'Rajasthan', pincode: '302017' },
    { id: 78, name: 'Vaishali Nagar', area: 'Vaishali Nagar', city: 'Jaipur', state: 'Rajasthan', pincode: '302021' },

    // Punjab
    { id: 79, name: 'Sector 17', area: 'Sector 17', city: 'Chandigarh', state: 'Punjab', pincode: '160017' },
    { id: 80, name: 'Sector 35', area: 'Sector 35', city: 'Chandigarh', state: 'Punjab', pincode: '160035' }
];

// Default saved addresses
const defaultAddresses = [
    { id: 1, name: 'Home', address: 'Sector 15, Gurgaon, Haryana 122001', type: 'home', isDefault: true },
    { id: 2, name: 'Work', address: 'Cyber City, DLF Phase 2, Gurgaon 122002', type: 'work', isDefault: false },
    { id: 3, name: 'Other', address: 'MG Road, Gurgaon, Haryana 122001', type: 'other', isDefault: false }
];

// Location state
let savedAddresses = [];
let currentLocation = null;
let searchDebounceTimer = null;
let isDropdownOpen = false;

/**
 * Initialize location functionality
 */
function initLocationSelector() {
    // Load saved addresses from localStorage
    loadSavedAddresses();

    // Set current location
    loadCurrentLocation();

    // Setup event listeners
    setupLocationEventListeners();

    // Render saved addresses in modal
    renderSavedAddresses();

    // Create autocomplete dropdown container
    createAutocompleteDropdown();
}

/**
 * Create autocomplete dropdown container
 */
function createAutocompleteDropdown() {
    // Remove existing if any
    $('#location-autocomplete-dropdown').remove();

    // Create dropdown container
    const dropdownHTML = `
        <div id="location-autocomplete-dropdown" class="location-autocomplete-dropdown">
            <div class="autocomplete-results"></div>
        </div>
    `;

    // Append to body for proper z-index handling
    $('body').append(dropdownHTML);
}

/**
 * Load saved addresses from localStorage
 */
function loadSavedAddresses() {
    const saved = localStorage.getItem('savedAddresses');
    if (saved) {
        savedAddresses = JSON.parse(saved);
    } else {
        savedAddresses = defaultAddresses;
        saveSavedAddresses();
    }
}

/**
 * Save addresses to localStorage
 */
function saveSavedAddresses() {
    localStorage.setItem('savedAddresses', JSON.stringify(savedAddresses));
}

/**
 * Load current location from localStorage
 * Priority: 1. Customer saved address, 2. currentLocation, 3. Default address
 */
function loadCurrentLocation() {
    // First check if user has saved address in customer details
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        if (user.address && user.address.trim()) {
            // Use customer's saved address
            currentLocation = {
                name: user.address.split(',')[0].trim() || 'Home',
                address: user.address,
                city: user.city || '',
                pincode: user.pincode || '',
                isCustomerAddress: true
            };
            updateLocationDisplay();
            return;
        }
    }

    // Fallback to saved currentLocation
    const saved = localStorage.getItem('currentLocation');
    if (saved) {
        currentLocation = JSON.parse(saved);
    } else {
        currentLocation = savedAddresses.find(addr => addr.isDefault) || savedAddresses[0];
        if (currentLocation) {
            saveCurrentLocation();
        }
    }
    updateLocationDisplay();
}

/**
 * Save current location to localStorage
 */
function saveCurrentLocation() {
    localStorage.setItem('currentLocation', JSON.stringify(currentLocation));
}

/**
 * Update the location display in header
 */
function updateLocationDisplay() {
    const locationText = $('#current-location');
    if (currentLocation) {
        // Show address with city if available
        let displayText = currentLocation.name;
        if (currentLocation.city && !currentLocation.isCustomerAddress) {
            displayText += `, ${currentLocation.city}`;
        }
        // Truncate if too long
        if (displayText.length > 25) {
            displayText = displayText.substring(0, 22) + '...';
        }
        locationText.text(`Delivering to: ${displayText}`);
    } else {
        locationText.text('Select Location');
    }
}

/**
 * Setup event listeners for location functionality
 */
function setupLocationEventListeners() {
    // Open location modal when clicking on location selector
    $('#location-selector').on('click', function() {
        openLocationModal();
    });

    // Close location modal
    $('#close-location-modal').on('click', function() {
        closeLocationModal();
    });

    // Close modal on overlay click
    $('#location-modal').on('click', function(e) {
        if (e.target === this) {
            closeLocationModal();
        }
    });

    // Use current location button
    $('#use-current-location-btn').on('click', function() {
        getCurrentDeviceLocation();
    });

    // Search input for locations with debounce
    $('#location-search-input').on('input', function() {
        const query = $(this).val().trim();

        // Clear previous timer
        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }

        if (query.length < 2) {
            hideAutocompleteDropdown();
            showSavedAddresses();
            return;
        }

        // Show loading state
        showAutocompleteLoading();

        // Debounce search - 300ms
        searchDebounceTimer = setTimeout(() => {
            searchLocations(query);
        }, 300);
    });

    // Focus on search input
    $('#location-search-input').on('focus', function() {
        const query = $(this).val().trim();
        if (query.length >= 2) {
            searchLocations(query);
        }
    });

    // Handle selecting a saved address
    $(document).on('click', '.saved-address-item', function() {
        const addressId = $(this).data('id');
        selectSavedAddress(addressId);
    });

    // Handle selecting an autocomplete result
    $(document).on('click', '.autocomplete-item', function() {
        const locationId = $(this).data('id');
        selectAutocompleteResult(locationId);
    });

    // Handle adding new address
    $(document).on('click', '#add-new-address-btn', function() {
        openAddAddressForm();
    });

    // Handle saving new address
    $(document).on('click', '#save-new-address-btn', function() {
        saveNewAddress();
    });

    // Handle canceling new address
    $(document).on('click', '#cancel-new-address-btn', function() {
        closeAddAddressForm();
    });

    // Handle deleting an address
    $(document).on('click', '.delete-address-btn', function(e) {
        e.stopPropagation();
        const addressId = $(this).closest('.saved-address-item').data('id');
        deleteAddress(addressId);
    });

    // Close dropdown on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#location-search-input, #location-autocomplete-dropdown').length) {
            hideAutocompleteDropdown();
        }
    });

    // Handle keyboard navigation
    $('#location-search-input').on('keydown', function(e) {
        handleKeyboardNavigation(e);
    });
}

/**
 * Handle keyboard navigation in autocomplete
 */
function handleKeyboardNavigation(e) {
    const dropdown = $('#location-autocomplete-dropdown');
    const items = dropdown.find('.autocomplete-item');
    const activeItem = dropdown.find('.autocomplete-item.active');

    if (!isDropdownOpen || items.length === 0) return;

    switch(e.keyCode) {
        case 40: // Arrow Down
            e.preventDefault();
            if (activeItem.length === 0) {
                items.first().addClass('active');
            } else {
                const nextItem = activeItem.removeClass('active').next('.autocomplete-item');
                if (nextItem.length) {
                    nextItem.addClass('active');
                } else {
                    items.first().addClass('active');
                }
            }
            scrollToActiveItem();
            break;

        case 38: // Arrow Up
            e.preventDefault();
            if (activeItem.length === 0) {
                items.last().addClass('active');
            } else {
                const prevItem = activeItem.removeClass('active').prev('.autocomplete-item');
                if (prevItem.length) {
                    prevItem.addClass('active');
                } else {
                    items.last().addClass('active');
                }
            }
            scrollToActiveItem();
            break;

        case 13: // Enter
            e.preventDefault();
            if (activeItem.length) {
                activeItem.click();
            }
            break;

        case 27: // Escape
            hideAutocompleteDropdown();
            break;
    }
}

/**
 * Scroll to active item in dropdown
 */
function scrollToActiveItem() {
    const dropdown = $('#location-autocomplete-dropdown .autocomplete-results');
    const activeItem = dropdown.find('.autocomplete-item.active');

    if (activeItem.length) {
        const itemTop = activeItem.position().top;
        const itemHeight = activeItem.outerHeight();
        const dropdownHeight = dropdown.height();
        const scrollTop = dropdown.scrollTop();

        if (itemTop < 0) {
            dropdown.scrollTop(scrollTop + itemTop);
        } else if (itemTop + itemHeight > dropdownHeight) {
            dropdown.scrollTop(scrollTop + itemTop + itemHeight - dropdownHeight);
        }
    }
}

/**
 * Search locations with simulated API call
 */
function searchLocations(query) {
    // Simulate API call with setTimeout
    setTimeout(() => {
        const queryLower = query.toLowerCase();

        // Search in location database
        const results = locationDatabase.filter(loc => {
            return loc.name.toLowerCase().includes(queryLower) ||
                   loc.area.toLowerCase().includes(queryLower) ||
                   loc.city.toLowerCase().includes(queryLower) ||
                   loc.pincode.includes(query);
        }).slice(0, 10); // Limit to 10 results

        renderAutocompleteResults(results, query);
    }, 100); // Simulate network delay
}

/**
 * Show autocomplete loading state
 */
function showAutocompleteLoading() {
    const dropdown = $('#location-autocomplete-dropdown');
    const resultsContainer = dropdown.find('.autocomplete-results');

    resultsContainer.html(`
        <div class="autocomplete-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Searching locations...</span>
        </div>
    `);

    positionAndShowDropdown();
}

/**
 * Render autocomplete results
 */
function renderAutocompleteResults(results, query) {
    const dropdown = $('#location-autocomplete-dropdown');
    const resultsContainer = dropdown.find('.autocomplete-results');

    if (results.length === 0) {
        resultsContainer.html(`
            <div class="autocomplete-no-results">
                <i class="fas fa-map-marker-alt"></i>
                <span>No locations found for "${query}"</span>
                <p>Try searching with a different area or pincode</p>
            </div>
        `);
    } else {
        const resultsHTML = results.map((loc, index) => {
            // Highlight matching text
            const highlightedName = highlightMatch(loc.name, query);
            const highlightedArea = highlightMatch(loc.area, query);

            return `
                <div class="autocomplete-item ${index === 0 ? 'active' : ''}" data-id="${loc.id}">
                    <div class="autocomplete-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="autocomplete-content">
                        <div class="autocomplete-name">${highlightedName}</div>
                        <div class="autocomplete-address">${highlightedArea}, ${loc.city}, ${loc.state} - ${loc.pincode}</div>
                    </div>
                </div>
            `;
        }).join('');

        resultsContainer.html(resultsHTML);
    }

    positionAndShowDropdown();
    hideSavedAddresses();
}

/**
 * Highlight matching text in search results
 */
function highlightMatch(text, query) {
    if (!query) return text;
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

/**
 * Escape regex special characters
 */
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Position and show autocomplete dropdown
 */
function positionAndShowDropdown() {
    const input = $('#location-search-input');
    const dropdown = $('#location-autocomplete-dropdown');

    if (!input.length) return;

    const inputOffset = input.offset();
    const inputHeight = input.outerHeight();
    const inputWidth = input.outerWidth();

    dropdown.css({
        top: inputOffset.top + inputHeight + 4,
        left: inputOffset.left,
        width: inputWidth,
        display: 'block'
    });

    isDropdownOpen = true;
}

/**
 * Hide autocomplete dropdown
 */
function hideAutocompleteDropdown() {
    $('#location-autocomplete-dropdown').hide();
    isDropdownOpen = false;
}

/**
 * Show saved addresses section
 */
function showSavedAddresses() {
    $('#saved-addresses-section').show();
    $('#location-search-results').hide();
}

/**
 * Hide saved addresses section when showing autocomplete
 */
function hideSavedAddresses() {
    // Keep saved addresses visible but show results above
}

/**
 * Select autocomplete result
 */
function selectAutocompleteResult(locationId) {
    const location = locationDatabase.find(loc => loc.id === locationId);

    if (location) {
        currentLocation = {
            id: Date.now(),
            name: location.name,
            address: `${location.area}, ${location.city}, ${location.state} - ${location.pincode}`,
            type: 'other',
            isDefault: false
        };

        saveCurrentLocation();
        updateLocationDisplay();
        hideAutocompleteDropdown();
        closeLocationModal();
        Toast.success(`Delivering to ${location.name}, ${location.city}`);
    }
}

/**
 * Open location modal
 */
function openLocationModal() {
    renderSavedAddresses();
    $('#location-modal').fadeIn(200);
    $('#location-search-input').val('').focus();
    hideAutocompleteDropdown();
    showSavedAddresses();
}

/**
 * Close location modal
 */
function closeLocationModal() {
    $('#location-modal').fadeOut(200);
    hideAutocompleteDropdown();
}

/**
 * Render saved addresses in modal
 */
function renderSavedAddresses() {
    const container = $('#saved-addresses-list');
    container.empty();

    if (savedAddresses.length === 0) {
        container.html(`
            <div class="no-addresses">
                <i class="fas fa-map-marker-alt"></i>
                <p>No saved addresses yet</p>
            </div>
        `);
    } else {
        savedAddresses.forEach(address => {
            const isSelected = currentLocation && currentLocation.id === address.id;
            const iconClass = address.type === 'home' ? 'fa-home' :
                              address.type === 'work' ? 'fa-briefcase' : 'fa-map-marker-alt';

            const addressHTML = `
                <div class="saved-address-item ${isSelected ? 'selected' : ''}" data-id="${address.id}">
                    <div class="address-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="address-content">
                        <div class="address-name">${address.name}</div>
                        <div class="address-text">${address.address}</div>
                    </div>
                    ${isSelected ? '<i class="fas fa-check selected-icon"></i>' : ''}
                    <button class="delete-address-btn" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            container.append(addressHTML);
        });
    }

    // Add "Add New Address" button
    container.append(`
        <button class="add-address-btn" id="add-new-address-btn">
            <i class="fas fa-plus"></i>
            <span>Add New Address</span>
        </button>
    `);
}

/**
 * Select a saved address
 */
function selectSavedAddress(addressId) {
    const address = savedAddresses.find(addr => addr.id === addressId);
    if (address) {
        currentLocation = address;
        saveCurrentLocation();
        updateLocationDisplay();
        closeLocationModal();
        Toast.success(`Delivering to ${address.name}`);
    }
}

/**
 * Get current device location using Geolocation API
 */
function getCurrentDeviceLocation() {
    if (!navigator.geolocation) {
        Toast.error('Geolocation is not supported by your browser');
        return;
    }

    Loading.show();

    navigator.geolocation.getCurrentPosition(
        function(position) {
            Loading.hide();
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Since we don't have reverse geocoding API, show coordinates
            currentLocation = {
                id: Date.now(),
                name: 'Current Location',
                address: `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`,
                type: 'other',
                isDefault: false
            };
            saveCurrentLocation();
            updateLocationDisplay();
            closeLocationModal();
            Toast.success('Location detected successfully!');
        },
        function(error) {
            Loading.hide();
            let errorMsg = 'Unable to get your location';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = 'Location permission denied. Please enable location access.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMsg = 'Location request timed out.';
                    break;
            }
            Toast.error(errorMsg);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

/**
 * Open add new address form
 */
function openAddAddressForm() {
    const formHTML = `
        <div class="add-address-form" id="add-address-form">
            <h4>Add New Address</h4>
            <div class="form-group">
                <label>Address Type</label>
                <div class="address-type-selector">
                    <label class="type-option">
                        <input type="radio" name="addressType" value="home" checked>
                        <span><i class="fas fa-home"></i> Home</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="addressType" value="work">
                        <span><i class="fas fa-briefcase"></i> Work</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="addressType" value="other">
                        <span><i class="fas fa-map-marker-alt"></i> Other</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label>Address Name *</label>
                <input type="text" id="new-address-name" class="form-control" placeholder="e.g., Home, Office, Mom's House">
            </div>
            <div class="form-group">
                <label>Full Address *</label>
                <textarea id="new-address-full" class="form-control" rows="3" placeholder="Enter complete address with landmark"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="cancel-new-address-btn">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-new-address-btn">Save Address</button>
            </div>
        </div>
    `;

    $('#saved-addresses-list').html(formHTML);
}

/**
 * Close add address form
 */
function closeAddAddressForm() {
    renderSavedAddresses();
}

/**
 * Save new address
 */
function saveNewAddress() {
    const name = $('#new-address-name').val().trim();
    const address = $('#new-address-full').val().trim();
    const type = $('input[name="addressType"]:checked').val();

    if (!name) {
        Toast.error('Please enter an address name');
        return;
    }

    if (!address) {
        Toast.error('Please enter the full address');
        return;
    }

    const newAddress = {
        id: Date.now(),
        name: name,
        address: address,
        type: type,
        isDefault: savedAddresses.length === 0
    };

    savedAddresses.push(newAddress);
    saveSavedAddresses();

    // Select the new address
    currentLocation = newAddress;
    saveCurrentLocation();
    updateLocationDisplay();

    Toast.success('Address saved successfully!');
    renderSavedAddresses();
}

/**
 * Delete an address
 */
function deleteAddress(addressId) {
    if (savedAddresses.length <= 1) {
        Toast.error('You must have at least one address');
        return;
    }

    savedAddresses = savedAddresses.filter(addr => addr.id !== addressId);
    saveSavedAddresses();

    // If deleted address was current, select first available
    if (currentLocation && currentLocation.id === addressId) {
        currentLocation = savedAddresses[0];
        saveCurrentLocation();
        updateLocationDisplay();
    }

    Toast.success('Address deleted');
    renderSavedAddresses();
}

// Initialize when document is ready
$(document).ready(function() {
    initLocationSelector();
});

// Make refreshLocationDisplay globally available so it can be called when customer details are saved
window.refreshLocationDisplay = function() {
    loadCurrentLocation();
};
