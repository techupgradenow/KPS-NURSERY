/**
 * Login & Signup Functionality
 * KPS Nursery App
 * Handles mobile number authentication with OTP verification
 */

// State Management
const LoginState = {
    mobile: '',
    email: '',
    otp: '',
    isNewUser: false,
    otpTimer: null,
    otpTimeLeft: 30,
    loginMethod: 'mobile' // 'mobile' or 'email'
};

// Initialize on DOM ready
$(document).ready(function() {
    initializeLogin();
});

/**
 * Initialize Login Page
 */
function initializeLogin() {
    // Check if user is already logged in
    const user = AppState.loadUser();
    if (user && user.id && user.mobile && !user.mobile.startsWith('GUEST')) {
        // Already logged in, redirect to home
        window.location.href = 'home.html';
        return;
    }

    // Initialize event handlers
    initMobileForm();
    initEmailForm();
    initLoginMethodToggle();
    initOTPForm();
    initProfileForm();
    initGuestLogin();

    // Auto-focus mobile input
    $('#mobile-input').focus();
}

/**
 * Initialize Login Method Toggle
 */
function initLoginMethodToggle() {
    // Mobile toggle button
    $('#toggle-mobile').on('click', function() {
        if (!$(this).hasClass('active')) {
            $(this).addClass('active');
            $('#toggle-email').removeClass('active');
            $('#mobile-form').removeClass('hidden');
            $('#email-form').addClass('hidden');
            LoginState.loginMethod = 'mobile';
            $('#mobile-input').focus();
        }
    });

    // Email toggle button
    $('#toggle-email').on('click', function() {
        if (!$(this).hasClass('active')) {
            $(this).addClass('active');
            $('#toggle-mobile').removeClass('active');
            $('#email-form').removeClass('hidden');
            $('#mobile-form').addClass('hidden');
            LoginState.loginMethod = 'email';
            $('#email-login-input').focus();
        }
    });
}

/**
 * Initialize Mobile Number Form
 */
function initMobileForm() {
    // Allow only numbers in mobile input
    $('#mobile-input').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Handle mobile form submission
    $('#mobile-form').on('submit', function(e) {
        e.preventDefault();
        sendOTP();
    });
}

/**
 * Initialize Email Form
 */
function initEmailForm() {
    // Handle email form submission
    $('#email-form').on('submit', function(e) {
        e.preventDefault();
        sendEmailOTP();
    });
}

/**
 * Send OTP to Mobile Number
 */
function sendOTP() {
    const mobile = $('#mobile-input').val().trim();

    // Validate mobile number
    if (!mobile || mobile.length !== 10) {
        Toast.error('Please enter a valid 10-digit mobile number');
        return;
    }

    // Validate starts with valid digit
    if (!/^[6-9]/.test(mobile)) {
        Toast.error('Mobile number should start with 6, 7, 8, or 9');
        return;
    }

    LoginState.mobile = mobile;
    Loading.show();

    // Call API to send OTP
    $.ajax({
        url: API_ENDPOINTS.auth,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'send_otp',
            mobile: mobile
        }),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                LoginState.isNewUser = response.is_new_user || false;
                showOTPStep();
                Toast.success('OTP sent to +91 ' + mobile);
            } else {
                Toast.error(response.message || 'Failed to send OTP');
            }
        },
        error: function(xhr) {
            Loading.hide();
            // For demo/development - proceed even if API fails
            console.log('API not available, using demo mode');
            LoginState.isNewUser = true;
            showOTPStep();
            Toast.info('Demo mode: Use OTP 123456');
        }
    });
}

/**
 * Send OTP to Email Address
 */
function sendEmailOTP() {
    const email = $('#email-login-input').val().trim();

    // Validate email
    if (!email || !isValidEmail(email)) {
        Toast.error('Please enter a valid email address');
        return;
    }

    LoginState.email = email;
    Loading.show();

    // Call API to send OTP
    $.ajax({
        url: API_ENDPOINTS.auth,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'send_email_otp',
            email: email
        }),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                LoginState.isNewUser = response.is_new_user || false;
                showOTPStep();
                Toast.success('OTP sent to ' + email);
            } else {
                Toast.error(response.message || 'Failed to send OTP');
            }
        },
        error: function(xhr) {
            Loading.hide();
            // For demo/development - proceed even if API fails
            console.log('API not available, using demo mode');
            LoginState.isNewUser = true;
            showOTPStep();
            Toast.info('Demo mode: Use OTP 123456');
        }
    });
}

/**
 * Show OTP Verification Step
 */
function showOTPStep() {
    // Update display based on login method
    if (LoginState.loginMethod === 'email') {
        $('#display-mobile').text(LoginState.email);
    } else {
        $('#display-mobile').text('+91 ' + LoginState.mobile);
    }

    // Switch steps
    $('#step-mobile').addClass('hidden');
    $('#step-otp').removeClass('hidden');

    // Focus first OTP input
    $('.otp-input').first().focus();

    // Start resend timer
    startResendTimer();
}

/**
 * Initialize OTP Form
 */
function initOTPForm() {
    // OTP input handling
    $('.otp-input').on('input', function() {
        const $this = $(this);
        const value = $this.val().replace(/[^0-9]/g, '');
        $this.val(value);

        // Move to next input if value entered
        if (value && $this.data('index') < 5) {
            $('.otp-input').eq($this.data('index') + 1).focus();
        }

        // Check if all inputs are filled
        checkOTPComplete();
    });

    // Handle backspace
    $('.otp-input').on('keydown', function(e) {
        const $this = $(this);
        if (e.key === 'Backspace' && !$this.val() && $this.data('index') > 0) {
            $('.otp-input').eq($this.data('index') - 1).focus();
        }
    });

    // Handle paste
    $('.otp-input').first().on('paste', function(e) {
        e.preventDefault();
        const pastedData = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        const digits = pastedData.replace(/[^0-9]/g, '').slice(0, 6);

        digits.split('').forEach((digit, index) => {
            $('.otp-input').eq(index).val(digit);
        });

        checkOTPComplete();
        if (digits.length === 6) {
            $('.otp-input').last().focus();
        }
    });

    // Back button
    $('#back-to-mobile').on('click', function() {
        $('#step-otp').addClass('hidden');
        $('#step-mobile').removeClass('hidden');
        clearOTPInputs();
        stopResendTimer();
        // Focus correct input based on method
        if (LoginState.loginMethod === 'email') {
            $('#email-login-input').focus();
        } else {
            $('#mobile-input').focus();
        }
    });

    // Resend OTP button
    $('#resend-otp-btn').on('click', function() {
        resendOTP();
    });

    // OTP form submission
    $('#otp-form').on('submit', function(e) {
        e.preventDefault();
        verifyOTP();
    });
}

/**
 * Check if OTP is complete
 */
function checkOTPComplete() {
    let otp = '';
    $('.otp-input').each(function() {
        otp += $(this).val();
    });

    LoginState.otp = otp;

    if (otp.length === 6) {
        $('#verify-otp-btn').prop('disabled', false);
    } else {
        $('#verify-otp-btn').prop('disabled', true);
    }
}

/**
 * Clear OTP Inputs
 */
function clearOTPInputs() {
    $('.otp-input').val('');
    $('#verify-otp-btn').prop('disabled', true);
    LoginState.otp = '';
}

/**
 * Start Resend Timer
 */
function startResendTimer() {
    LoginState.otpTimeLeft = 30;
    updateTimerDisplay();

    $('#resend-timer').removeClass('hidden');
    $('#resend-otp-btn').addClass('hidden');

    LoginState.otpTimer = setInterval(function() {
        LoginState.otpTimeLeft--;
        updateTimerDisplay();

        if (LoginState.otpTimeLeft <= 0) {
            stopResendTimer();
            $('#resend-timer').addClass('hidden');
            $('#resend-otp-btn').removeClass('hidden');
        }
    }, 1000);
}

/**
 * Update Timer Display
 */
function updateTimerDisplay() {
    $('#resend-timer').html(`Resend OTP in <strong>${LoginState.otpTimeLeft}s</strong>`);
}

/**
 * Stop Resend Timer
 */
function stopResendTimer() {
    if (LoginState.otpTimer) {
        clearInterval(LoginState.otpTimer);
        LoginState.otpTimer = null;
    }
}

/**
 * Resend OTP
 */
function resendOTP() {
    Loading.show();
    clearOTPInputs();

    // Determine action and data based on login method
    let requestData;
    if (LoginState.loginMethod === 'email') {
        requestData = {
            action: 'resend_email_otp',
            email: LoginState.email
        };
    } else {
        requestData = {
            action: 'resend_otp',
            mobile: LoginState.mobile
        };
    }

    $.ajax({
        url: API_ENDPOINTS.auth,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(requestData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                startResendTimer();
                Toast.success('OTP sent again');
                $('.otp-input').first().focus();
            } else {
                Toast.error(response.message || 'Failed to resend OTP');
            }
        },
        error: function() {
            Loading.hide();
            // Demo mode
            startResendTimer();
            Toast.info('Demo: OTP resent (use 123456)');
            $('.otp-input').first().focus();
        }
    });
}

/**
 * Verify OTP
 */
function verifyOTP() {
    if (LoginState.otp.length !== 6) {
        Toast.error('Please enter complete OTP');
        return;
    }

    Loading.show();

    // Determine action and data based on login method
    let requestData;
    if (LoginState.loginMethod === 'email') {
        requestData = {
            action: 'verify_email_otp',
            email: LoginState.email,
            otp: LoginState.otp
        };
    } else {
        requestData = {
            action: 'verify_otp',
            mobile: LoginState.mobile,
            otp: LoginState.otp
        };
    }

    $.ajax({
        url: API_ENDPOINTS.auth,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(requestData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                stopResendTimer();

                if (response.user) {
                    // Existing user - save and redirect
                    AppState.saveUser(response.user);
                    Toast.success('Welcome back!');
                    redirectToHome();
                } else if (response.is_new_user) {
                    // New user - show profile setup
                    showProfileStep();
                }
            } else {
                Toast.error(response.message || 'Invalid OTP');
                clearOTPInputs();
                $('.otp-input').first().focus();
            }
        },
        error: function() {
            Loading.hide();
            // Demo mode - check for demo OTP
            if (LoginState.otp === '123456') {
                stopResendTimer();
                // Check if new user (demo)
                if (LoginState.isNewUser) {
                    showProfileStep();
                } else {
                    // Create demo user
                    const demoUser = {
                        id: Date.now(),
                        mobile: LoginState.loginMethod === 'email' ? '' : LoginState.mobile,
                        email: LoginState.loginMethod === 'email' ? LoginState.email : null,
                        name: 'User',
                        is_guest: 0
                    };
                    AppState.saveUser(demoUser);
                    Toast.success('Login successful!');
                    redirectToHome();
                }
            } else {
                Toast.error('Invalid OTP. Demo: Use 123456');
                clearOTPInputs();
                $('.otp-input').first().focus();
            }
        }
    });
}

/**
 * Show Profile Setup Step
 */
function showProfileStep() {
    $('#step-otp').addClass('hidden');
    $('#step-profile').removeClass('hidden');

    // Pre-fill email if logged in via email
    if (LoginState.loginMethod === 'email' && LoginState.email) {
        $('#email-input').val(LoginState.email);
        $('#email-input').prop('readonly', true);
    }

    $('#name-input').focus();
}

/**
 * Initialize Profile Form
 */
function initProfileForm() {
    // Profile form submission
    $('#profile-form').on('submit', function(e) {
        e.preventDefault();
        completeProfile();
    });

    // Skip profile button
    $('#skip-profile-btn').on('click', function() {
        completeProfile(true);
    });
}

/**
 * Complete Profile Setup
 */
function completeProfile(skip = false) {
    const name = $('#name-input').val().trim();
    let email = $('#email-input').val().trim();

    if (!skip && !name) {
        Toast.error('Please enter your name');
        $('#name-input').focus();
        return;
    }

    // If login method was email, use that email
    if (LoginState.loginMethod === 'email' && LoginState.email) {
        email = LoginState.email;
    }

    // Validate email if provided
    if (email && !isValidEmail(email)) {
        Toast.error('Please enter a valid email address');
        $('#email-input').focus();
        return;
    }

    Loading.show();

    // Register user via API
    $.ajax({
        url: API_ENDPOINTS.users,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'login',
            mobile: LoginState.loginMethod === 'email' ? '' : LoginState.mobile,
            name: name || 'User',
            email: email || null
        }),
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success && response.data) {
                AppState.saveUser(response.data);
                Toast.success('Welcome to KPS Nursery!');
                redirectToHome();
            } else {
                Toast.error(response.message || 'Registration failed');
            }
        },
        error: function() {
            Loading.hide();
            // Demo mode - create local user
            const newUser = {
                id: Date.now(),
                mobile: LoginState.loginMethod === 'email' ? '' : LoginState.mobile,
                name: name || 'User',
                email: email || null,
                is_guest: 0,
                created_at: new Date().toISOString()
            };
            AppState.saveUser(newUser);
            Toast.success('Welcome to KPS Nursery!');
            redirectToHome();
        }
    });
}

/**
 * Initialize Guest Login
 */
function initGuestLogin() {
    $('#guest-login-btn').on('click', function() {
        Loading.show();

        $.ajax({
            url: API_ENDPOINTS.users,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'guest'
            }),
            dataType: 'json',
            success: function(response) {
                Loading.hide();
                if (response.success && response.data) {
                    AppState.saveUser(response.data);
                    Toast.success('Welcome, Guest!');
                    redirectToHome();
                } else {
                    Toast.error('Failed to continue as guest');
                }
            },
            error: function() {
                Loading.hide();
                // Demo mode - create local guest
                const guestUser = {
                    id: 'GUEST' + Date.now(),
                    mobile: 'GUEST' + Date.now(),
                    name: 'Guest User',
                    is_guest: 1
                };
                AppState.saveUser(guestUser);
                Toast.info('Continuing as Guest');
                redirectToHome();
            }
        });
    });
}

/**
 * Redirect to Home Page
 */
function redirectToHome() {
    // Check if there's a redirect URL stored
    const redirectUrl = sessionStorage.getItem('redirect_after_login');
    if (redirectUrl) {
        sessionStorage.removeItem('redirect_after_login');
        window.location.href = redirectUrl;
    } else {
        window.location.href = 'home.html';
    }
}

/**
 * Validate Email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Open Terms Modal
 */
function openTerms() {
    Toast.info('Terms of Service: Coming soon!');
}

/**
 * Open Privacy Modal
 */
function openPrivacy() {
    Toast.info('Privacy Policy: Coming soon!');
}
