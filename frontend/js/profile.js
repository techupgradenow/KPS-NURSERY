/**
 * Profile Page JavaScript
 * KPS Nursery App
 */

$(document).ready(function() {
    // Load user profile
    loadProfile();

    // Setup event listeners
    setupEventListeners();
});

/**
 * Load User Profile
 * LOGIN TEMPORARILY DISABLED - Users can edit their profile without login
 */
function loadProfile() {
    const user = AppState.loadUser();

    // === LOGIN TEMPORARILY DISABLED ===
    // Remove any existing login prompt (in case it was added before)
    $('#login-prompt').remove();

    // Always show profile edit capabilities without requiring login
    if (user && user.name) {
        // User has saved their details - show them
        $('#profile-name').text(user.name || 'User');
        if (user.mobile) {
            $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>+91 ' + user.mobile + '</span>');
        } else {
            $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>Tap to add mobile</span>');
        }
        if (user.email) {
            $('#profile-email').show().html('<i class="fas fa-envelope"></i> <span>' + user.email + '</span>');
        } else {
            $('#profile-email').hide();
        }
        // Hide both login and logout buttons - login disabled
        $('#logout-btn').hide();
        $('#login-btn').hide();
    } else {
        // No user data yet - show welcome message
        $('#profile-name').text('Welcome User');
        $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>Tap to add your details</span>');
        $('#profile-email').hide();
        // Hide both login and logout buttons - login disabled
        $('#logout-btn').hide();
        $('#login-btn').hide();
    }

    /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
    if (user && user.mobile && !user.mobile.startsWith('GUEST')) {
        // Logged in user - fetch fresh data from API
        fetchUserProfile(user.id);
        // Show logout button, hide login button
        $('#logout-btn').show();
        $('#login-btn').hide();
    } else {
        // Guest or no user
        $('#profile-name').text('Guest User');
        $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>Login to see details</span>');
        $('#profile-email').hide();
        // Show login button, hide logout button
        $('#logout-btn').hide();
        $('#login-btn').show();
        // Update profile header for guest
        showGuestProfile();
    }
    */
}

/**
 * Show Guest Profile UI
 * LOGIN TEMPORARILY DISABLED - This function is commented out
 */
function showGuestProfile() {
    // === LOGIN TEMPORARILY DISABLED ===
    // Don't show login prompt since login is disabled
    console.log('[KPS Nursery] Login prompt disabled');

    /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
    // Add login prompt to profile
    const loginPrompt = `
        <div class="login-prompt" id="login-prompt">
            <div class="login-prompt-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3>Welcome to KPS Nursery!</h3>
            <p>Login to access your orders, addresses, and personalized offers.</p>
            <button class="btn btn-primary" onclick="window.location.href='login.html'">
                <i class="fas fa-sign-in-alt"></i> Login / Sign Up
            </button>
        </div>
    `;

    // Insert login prompt if not exists
    if ($('#login-prompt').length === 0) {
        $('.profile-stats').after(loginPrompt);
    }
    */
}

/**
 * Fetch user profile from API
 */
function fetchUserProfile(userId) {
    $.ajax({
        url: API_ENDPOINTS.users + '?id=' + userId + '&stats=true',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const user = response.data;

                // Update local storage with fresh data
                AppState.saveUser(user);

                // Update UI
                $('#profile-name').text(user.name || 'User');
                $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>' + (user.mobile.startsWith('+91') ? user.mobile : '+91 ' + user.mobile) + '</span>');

                if (user.email) {
                    $('#profile-email').show().html('<i class="fas fa-envelope"></i> <span>' + user.email + '</span>');
                } else {
                    $('#profile-email').hide();
                }

                // Update stats from API response
                if (user.stats) {
                    $('#total-orders').text(user.stats.total_orders || 0);
                    $('#total-saved').text(user.stats.total_saved || 0);
                    $('#wallet-balance').text(user.stats.wallet_balance || 0);
                }

                // Update notification toggle based on DB value
                if (user.notifications_enabled == 1) {
                    $('#notification-toggle').addClass('active');
                } else {
                    $('#notification-toggle').removeClass('active');
                }

                // Update language preference from DB
                if (user.language) {
                    localStorage.setItem('app_language', user.language);
                    loadLanguagePreference();
                }
            }
        },
        error: function() {
            // Fallback to local data
            const user = AppState.loadUser();
            if (user) {
                $('#profile-name').text(user.name || 'User');
                $('#profile-mobile').html('<i class="fas fa-phone"></i> <span>' + (user.mobile.startsWith('+91') ? user.mobile : '+91 ' + user.mobile) + '</span>');
                if (user.email) {
                    $('#profile-email').show().html('<i class="fas fa-envelope"></i> <span>' + user.email + '</span>');
                }
            }
        }
    });
}

/**
 * Load Profile Stats (now handled by fetchUserProfile with stats=true param)
 */
function loadProfileStats(userId) {
    // Stats are now loaded via fetchUserProfile with stats=true parameter
    // This function is kept for backwards compatibility
    fetchUserProfile(userId);
}

/**
 * Open Edit Profile Modal
 */
function openEditProfileModal() {
    const user = AppState.loadUser();

    if (user) {
        $('#edit-profile-form input[name="name"]').val(user.name || '');
        $('#edit-profile-form input[name="email"]').val(user.email || '');
        $('#edit-profile-form input[name="mobile"]').val(user.mobile || '');
    }

    $('#edit-profile-modal').addClass('active');
}

/**
 * Close Edit Profile Modal
 */
function closeEditProfileModal() {
    $('#edit-profile-modal').removeClass('active');
}

/**
 * Save Profile
 */
function saveProfile(formData) {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        // Fallback to local storage for guests
        const guestUser = user || {};
        guestUser.name = formData.name;
        guestUser.email = formData.email;
        guestUser.mobile = formData.mobile;
        AppState.saveUser(guestUser);
        loadProfile();
        Toast.success('Profile updated successfully');
        closeEditProfileModal();
        return;
    }

    Loading.show();

    // Save to database via API
    $.ajax({
        url: API_ENDPOINTS.users,
        type: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({
            id: user.id,
            name: formData.name,
            email: formData.email
        }),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                // Update local storage with response data
                if (response.data) {
                    AppState.saveUser(response.data);
                }
                loadProfile();
                Toast.success(response.message || 'Profile updated successfully');
                closeEditProfileModal();
            } else {
                Toast.error(response.message || 'Failed to update profile');
            }
        },
        error: function(xhr) {
            Loading.hide();
            console.error('Profile update error:', xhr.responseText);
            Toast.error('Failed to update profile');
        }
    });
}

/**
 * Logout
 * LOGIN TEMPORARILY DISABLED - Just clears data and stays on page
 */
function logout() {
    // === LOGIN TEMPORARILY DISABLED ===
    if (confirm('Are you sure you want to clear your profile data?')) {
        AppState.logout();
        Toast.success('Profile data cleared');
        // Reload page instead of redirect to login
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }

    /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
    if (confirm('Are you sure you want to logout?')) {
        AppState.logout();
        Toast.success('Logged out successfully');

        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
    }
    */
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    // Edit profile button (header)
    $('#header-edit-btn').on('click', openEditProfileModal);

    // Close edit profile modal
    $('#close-edit-profile, #cancel-edit-profile').on('click', closeEditProfileModal);

    // Close modal on overlay click
    $('.modal-overlay').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Edit profile form submit
    $('#edit-profile-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            name: $(this).find('input[name="name"]').val(),
            email: $(this).find('input[name="email"]').val(),
            mobile: $(this).find('input[name="mobile"]').val()
        };

        // Validate
        if (!formData.name) {
            Toast.error('Name is required');
            return;
        }

        if (formData.mobile && !Utils.validateMobile(formData.mobile)) {
            Toast.error('Please enter a valid 10-digit mobile number');
            return;
        }

        if (formData.email && !Utils.validateEmail(formData.email)) {
            Toast.error('Please enter a valid email address');
            return;
        }

        saveProfile(formData);
    });

    // Notification toggle
    $('#notification-toggle').on('click', function() {
        $(this).toggleClass('active');
        const isActive = $(this).hasClass('active');

        // Save to database
        const user = AppState.loadUser();
        if (user && user.id) {
            $.ajax({
                url: API_ENDPOINTS.users,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'update_preferences',
                    user_id: user.id,
                    notifications_enabled: isActive
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Toast.success(isActive ? 'Notifications enabled' : 'Notifications disabled');
                        // Update local storage
                        user.notifications_enabled = isActive ? 1 : 0;
                        AppState.saveUser(user);
                    } else {
                        Toast.error('Failed to update notification preference');
                        // Revert toggle
                        $('#notification-toggle').toggleClass('active');
                    }
                },
                error: function() {
                    Toast.error('Failed to update notification preference');
                    $('#notification-toggle').toggleClass('active');
                }
            });
        } else {
            // Guest user - save locally only
            Toast.success(isActive ? 'Notifications enabled' : 'Notifications disabled');
            localStorage.setItem('notifications_enabled', isActive);
        }
    });

    // Manage addresses
    $('#manage-addresses-btn').on('click', function(e) {
        e.preventDefault();
        openAddressesModal();
    });

    // Close addresses modal
    $('#close-addresses-modal').on('click', function() {
        $('#addresses-modal').removeClass('active');
    });

    // Add new address button
    $('#add-new-address-btn').on('click', function() {
        openAddressFormModal();
    });

    // Close address form
    $('#close-address-form, #cancel-address-form').on('click', function() {
        $('#address-form-modal').removeClass('active');
    });

    // Address form submit
    $('#address-form').on('submit', function(e) {
        e.preventDefault();
        saveAddress();
    });

    // Payment methods
    $('#payment-methods-btn').on('click', function(e) {
        e.preventDefault();
        Toast.info('Payment methods: Coming soon!');
    });

    // Language selection
    $('#language-btn').on('click', function(e) {
        e.preventDefault();
        openLanguageModal();
    });

    // Close language modal
    $('#close-language-modal').on('click', function() {
        $('#language-modal').removeClass('active');
    });

    // Language option selection
    $(document).on('click', '.language-option', function() {
        const lang = $(this).data('lang');
        const langName = $(this).find('.lang-name').text().split(' ')[0];
        const $option = $(this);

        // Save to database
        const user = AppState.loadUser();
        if (user && user.id) {
            $.ajax({
                url: API_ENDPOINTS.users,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'update_preferences',
                    user_id: user.id,
                    language: lang
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.language-option').removeClass('active');
                        $option.addClass('active');
                        localStorage.setItem('app_language', lang);
                        $('#language-btn .menu-value span').text(langName);

                        // Update local storage
                        user.language = lang;
                        AppState.saveUser(user);

                        Toast.success(`Language changed to ${langName}`);
                        setTimeout(() => {
                            $('#language-modal').removeClass('active');
                        }, 500);
                    } else {
                        Toast.error('Failed to update language preference');
                    }
                },
                error: function() {
                    Toast.error('Failed to update language preference');
                }
            });
        } else {
            // Guest user - save locally only
            $('.language-option').removeClass('active');
            $option.addClass('active');
            localStorage.setItem('app_language', lang);
            $('#language-btn .menu-value span').text(langName);
            Toast.success(`Language changed to ${langName}`);
            setTimeout(() => {
                $('#language-modal').removeClass('active');
            }, 500);
        }
    });

    // Help & Support
    $('#help-support-btn').on('click', function(e) {
        e.preventDefault();
        openHelpModal();
    });

    // Close help modal
    $('#close-help-modal').on('click', function() {
        $('#help-modal').removeClass('active');
    });

    // About
    $('#about-btn').on('click', function(e) {
        e.preventDefault();
        openAboutModal();
    });

    // Close about modal
    $('#close-about-modal').on('click', function() {
        $('#about-modal').removeClass('active');
    });

    // Privacy Policy
    $('#privacy-btn').on('click', function(e) {
        e.preventDefault();
        openPrivacyModal();
    });

    // Close privacy modal
    $('#close-privacy-modal').on('click', function() {
        $('#privacy-modal').removeClass('active');
    });

    // Terms & Conditions
    $('#terms-btn').on('click', function(e) {
        e.preventDefault();
        openTermsModal();
    });

    // Close terms modal
    $('#close-terms-modal').on('click', function() {
        $('#terms-modal').removeClass('active');
    });

    // Load saved language preference
    loadLanguagePreference();

    // Logout
    $('#logout-btn').on('click', function(e) {
        e.preventDefault();
        logout();
    });
}

/**
 * Address Management Functions
 */

// Open Addresses Modal
// LOGIN TEMPORARILY DISABLED - Allow address management without login
function openAddressesModal() {
    const user = AppState.loadUser();

    // === LOGIN TEMPORARILY DISABLED ===
    // Allow address management even without user ID
    if (!user) {
        // Create a temporary user if none exists
        const tempUser = { id: 'TEMP_' + Date.now(), name: 'User' };
        AppState.saveUser(tempUser);
    }

    /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
    if (!user || !user.id) {
        Toast.error('Please login first');
        return;
    }
    */

    $('#addresses-modal').addClass('active');
    loadAddresses();
}

// Load user addresses
function loadAddresses() {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        return;
    }

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.addresses + '?user_id=' + user.id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success && response.data) {
                renderAddresses(response.data);
            } else {
                $('#addresses-list').html('<p style="text-align: center; color: #718096; padding: 2rem;">No saved addresses</p>');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to load addresses');
            $('#addresses-list').html('<p style="text-align: center; color: #718096; padding: 2rem;">Failed to load addresses</p>');
        }
    });
}

// Render addresses list
function renderAddresses(addresses) {
    if (!addresses || addresses.length === 0) {
        $('#addresses-list').html('<p style="text-align: center; color: #718096; padding: 2rem;">No saved addresses</p>');
        return;
    }

    const addressesHTML = addresses.map(addr => {
        const typeIcons = {
            home: 'fa-home',
            work: 'fa-briefcase',
            other: 'fa-map-marker-alt'
        };

        return `
            <div class="address-card ${addr.is_default == 1 ? 'default' : ''}">
                <span class="address-type"><i class="fas ${typeIcons[addr.type] || typeIcons.home}"></i> ${addr.type || 'Home'}</span>
                <div class="address-name">${addr.name}</div>
                <div class="address-text">${addr.address}${addr.landmark ? ', ' + addr.landmark : ''}, ${addr.city || 'Bangalore'} - ${addr.pincode}</div>
                <div class="address-mobile"><i class="fas fa-phone"></i> ${addr.mobile}</div>
                <div class="address-actions">
                    <button class="btn-edit" onclick="editAddress(${addr.id})"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-delete" onclick="deleteAddress(${addr.id})"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        `;
    }).join('');

    $('#addresses-list').html(addressesHTML);
}

// Open address form modal
function openAddressFormModal(addressId = null) {
    if (addressId) {
        $('#address-form-title').text('Edit Address');
        $('#address-id').val(addressId);

        const user = AppState.loadUser();
        Loading.show();

        $.ajax({
            url: API_ENDPOINTS.addresses + '?user_id=' + user.id,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                Loading.hide();
                if (response.success && response.data) {
                    const address = response.data.find(a => a.id == addressId);
                    if (address) {
                        $('#address-type').val(address.type);
                        $('#address-name').val(address.name);
                        $('#address-mobile').val(address.mobile);
                        $('#address-text').val(address.address);
                        $('#address-landmark').val(address.landmark || '');
                        $('#address-pincode').val(address.pincode);
                        $('#address-city').val(address.city || 'Bangalore');
                        $('#address-default').prop('checked', address.is_default == 1);
                    }
                }
                $('#addresses-modal').removeClass('active');
                $('#address-form-modal').addClass('active');
            },
            error: function() {
                Loading.hide();
                Toast.error('Failed to load address details');
            }
        });
    } else {
        $('#address-form-title').text('Add New Address');
        $('#address-id').val('');
        $('#address-form')[0].reset();
        $('#address-city').val('Bangalore');

        $('#addresses-modal').removeClass('active');
        $('#address-form-modal').addClass('active');
    }
}

// Save address
// LOGIN TEMPORARILY DISABLED - Allow saving address without strict login check
function saveAddress() {
    let user = AppState.loadUser();

    // === LOGIN TEMPORARILY DISABLED ===
    // Create a temporary user if none exists
    if (!user || !user.id) {
        user = { id: 'TEMP_' + Date.now(), name: 'User' };
        AppState.saveUser(user);
    }

    /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
    if (!user || !user.id) {
        Toast.error('Please login first');
        return;
    }
    */

    const addressId = $('#address-id').val();

    // Flat structure to match API expectations
    const formData = {
        user_id: user.id,
        type: $('#address-type').val(),
        name: $('#address-name').val().trim(),
        mobile: $('#address-mobile').val().trim(),
        address: $('#address-text').val().trim(),
        landmark: $('#address-landmark').val().trim(),
        pincode: $('#address-pincode').val().trim(),
        city: $('#address-city').val().trim(),
        is_default: $('#address-default').is(':checked') ? 1 : 0
    };

    // Validate
    if (!formData.name) {
        Toast.error('Name is required');
        return;
    }

    if (!formData.mobile || !Utils.validateMobile(formData.mobile)) {
        Toast.error('Please enter a valid 10-digit mobile number');
        return;
    }

    if (!formData.address) {
        Toast.error('Address is required');
        return;
    }

    if (!formData.pincode || !Utils.validatePincode(formData.pincode)) {
        Toast.error('Please enter a valid 6-digit pincode');
        return;
    }

    Loading.show();

    const url = API_ENDPOINTS.addresses;
    const method = addressId ? 'PUT' : 'POST';

    if (addressId) {
        formData.id = addressId;
    }

    $.ajax({
        url: url,
        type: method,
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                Toast.success(response.message || 'Address saved successfully');
                $('#address-form-modal').removeClass('active');
                $('#addresses-modal').addClass('active');
                loadAddresses();
            } else {
                Toast.error(response.message || 'Failed to save address');
            }
        },
        error: function(xhr) {
            Loading.hide();
            console.error('Save address error:', xhr.responseText);
            Toast.error('Failed to save address');
        }
    });
}

// Edit address
function editAddress(addressId) {
    openAddressFormModal(addressId);
}

// Delete address
function deleteAddress(addressId) {
    if (!confirm('Are you sure you want to delete this address?')) {
        return;
    }

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.addresses + '?id=' + addressId,
        type: 'DELETE',
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                Toast.success(response.message || 'Address deleted successfully');
                loadAddresses();
            } else {
                Toast.error(response.message || 'Failed to delete address');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to delete address');
        }
    });
}

/**
 * Language Functions
 */
function openLanguageModal() {
    const savedLang = localStorage.getItem('app_language') || 'en';
    $('.language-option').removeClass('active');
    $(`.language-option[data-lang="${savedLang}"]`).addClass('active');
    $('#language-modal').addClass('active');
}

function loadLanguagePreference() {
    const savedLang = localStorage.getItem('app_language') || 'en';
    const langNames = {
        'en': 'English',
        'hi': 'हिंदी',
        'ta': 'தமிழ்',
        'te': 'తెలుగు',
        'kn': 'ಕನ್ನಡ',
        'ml': 'മലയാളം'
    };
    $('#language-btn .menu-value span').text(langNames[savedLang] || 'English');
}

/**
 * Help & Support Functions
 */
function openHelpModal() {
    $('#help-modal').addClass('active');
}

function toggleFaq(element) {
    const faqItem = $(element).closest('.faq-item');
    const isOpen = faqItem.hasClass('open');

    // Close all FAQ items
    $('.faq-item').removeClass('open');

    // Toggle current item
    if (!isOpen) {
        faqItem.addClass('open');
    }
}

/**
 * About Modal
 */
function openAboutModal() {
    $('#about-modal').addClass('active');
}

/**
 * Privacy Policy Modal
 */
function openPrivacyModal() {
    $('#privacy-modal').addClass('active');
}

/**
 * Terms & Conditions Modal
 */
function openTermsModal() {
    $('#terms-modal').addClass('active');
}
