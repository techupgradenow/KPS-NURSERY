/**
 * Google Maps Location Integration
 * KPS Nursery App
 */

// Google Maps Configuration
const GOOGLE_MAPS = {
    autocompleteService: null,
    placesService: null,
    geocoder: null,
    sessionToken: null,
    initialized: false
};

/**
 * Initialize Google Maps Services
 * Called automatically when Google Maps API loads
 */
function initGoogleMaps() {
    try {
        // Initialize Google Maps services
        GOOGLE_MAPS.autocompleteService = new google.maps.places.AutocompleteService();
        GOOGLE_MAPS.geocoder = new google.maps.Geocoder();
        GOOGLE_MAPS.sessionToken = new google.maps.places.AutocompleteSessionToken();
        GOOGLE_MAPS.initialized = true;

        console.log('✅ Google Maps API initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing Google Maps:', error);
        GOOGLE_MAPS.initialized = false;
    }
}

/**
 * Search Locations using Google Places Autocomplete
 */
function searchLocationWithGoogle(query, callback) {
    if (!GOOGLE_MAPS.initialized) {
        console.error('Google Maps not initialized');
        callback([]);
        return;
    }

    if (!query || query.trim().length < 3) {
        callback([]);
        return;
    }

    const request = {
        input: query,
        sessionToken: GOOGLE_MAPS.sessionToken,
        componentRestrictions: { country: 'in' }, // Restrict to India
        types: ['geocode', 'establishment'] // Include addresses and places
    };

    GOOGLE_MAPS.autocompleteService.getPlacePredictions(request, (predictions, status) => {
        if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
            const locations = predictions.map(prediction => ({
                place_id: prediction.place_id,
                main_text: prediction.structured_formatting.main_text,
                secondary_text: prediction.structured_formatting.secondary_text,
                description: prediction.description,
                name: prediction.structured_formatting.main_text,
                full_address: prediction.description
            }));
            callback(locations);
        } else {
            console.error('Places Autocomplete error:', status);
            callback([]);
        }
    });
}

/**
 * Get Place Details (coordinates, formatted address)
 */
function getPlaceDetails(placeId, callback) {
    if (!GOOGLE_MAPS.initialized) {
        console.error('Google Maps not initialized');
        callback(null);
        return;
    }

    // Create a temporary div for PlacesService (required by Google Maps API)
    const mapDiv = document.createElement('div');
    const placesService = new google.maps.places.PlacesService(mapDiv);

    const request = {
        placeId: placeId,
        fields: ['geometry', 'formatted_address', 'name', 'address_components'],
        sessionToken: GOOGLE_MAPS.sessionToken
    };

    placesService.getDetails(request, (place, status) => {
        // Generate new session token after getting details
        GOOGLE_MAPS.sessionToken = new google.maps.places.AutocompleteSessionToken();

        if (status === google.maps.places.PlacesServiceStatus.OK && place) {
            const locationData = {
                place_id: placeId,
                name: place.name || '',
                address: place.formatted_address || '',
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng(),
                address_components: place.address_components || []
            };

            // Extract city and pincode from address components
            place.address_components.forEach(component => {
                if (component.types.includes('locality')) {
                    locationData.city = component.long_name;
                }
                if (component.types.includes('postal_code')) {
                    locationData.pincode = component.long_name;
                }
            });

            callback(locationData);
        } else {
            console.error('Place Details error:', status);
            callback(null);
        }
    });
}

/**
 * Get Current Location using Browser Geolocation
 * Then reverse geocode to get address
 */
function getCurrentLocationWithGoogle(callback) {
    if (!navigator.geolocation) {
        callback({
            success: false,
            message: 'Geolocation is not supported by your browser'
        });
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Reverse geocode to get address
            reverseGeocodeLocation(lat, lng, (location) => {
                if (location) {
                    callback({
                        success: true,
                        location: location
                    });
                } else {
                    callback({
                        success: false,
                        message: 'Could not determine your address'
                    });
                }
            });
        },
        (error) => {
            let message = 'Unable to get your location';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    message = 'Location permission denied';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information unavailable';
                    break;
                case error.TIMEOUT:
                    message = 'Location request timed out';
                    break;
            }
            callback({
                success: false,
                message: message
            });
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

/**
 * Reverse Geocode (Convert coordinates to address)
 */
function reverseGeocodeLocation(lat, lng, callback) {
    if (!GOOGLE_MAPS.initialized) {
        console.error('Google Maps not initialized');
        callback(null);
        return;
    }

    const latlng = { lat: lat, lng: lng };

    GOOGLE_MAPS.geocoder.geocode({ location: latlng }, (results, status) => {
        if (status === 'OK' && results[0]) {
            const place = results[0];
            const locationData = {
                name: place.formatted_address.split(',')[0],
                address: place.formatted_address,
                lat: lat,
                lng: lng,
                place_id: place.place_id,
                address_components: place.address_components || []
            };

            // Extract city and pincode
            place.address_components.forEach(component => {
                if (component.types.includes('locality')) {
                    locationData.city = component.long_name;
                }
                if (component.types.includes('postal_code')) {
                    locationData.pincode = component.long_name;
                }
            });

            callback(locationData);
        } else {
            console.error('Geocoder error:', status);
            callback(null);
        }
    });
}

/**
 * Calculate Distance Between Two Coordinates (Haversine formula)
 */
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLng / 2) * Math.sin(dLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distance = R * c;
    return distance; // in kilometers
}

/**
 * Validate Delivery Area
 * Check if location is within delivery radius
 */
function validateDeliveryWithGoogle(lat, lng, callback) {
    // Store location (example: Bangalore)
    const storeLocation = {
        lat: 12.9716, // Bangalore latitude
        lng: 77.5946  // Bangalore longitude
    };

    // Maximum delivery radius in km
    const maxDeliveryRadius = 15; // 15 km radius

    // Calculate distance
    const distance = calculateDistance(lat, lng, storeLocation.lat, storeLocation.lng);

    // Estimate delivery time (5 mins per km + 15 mins preparation)
    const deliveryTime = Math.ceil(15 + (distance * 5));

    if (distance <= maxDeliveryRadius) {
        callback({
            serviceable: true,
            distance: distance.toFixed(2),
            delivery_time: deliveryTime,
            message: `Delivery available in ${deliveryTime} minutes`
        });
    } else {
        callback({
            serviceable: false,
            distance: distance.toFixed(2),
            message: `Sorry, we don't deliver beyond ${maxDeliveryRadius}km. You're ${distance.toFixed(1)}km away.`
        });
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initGoogleMaps,
        searchLocationWithGoogle,
        getPlaceDetails,
        getCurrentLocationWithGoogle,
        reverseGeocodeLocation,
        validateDeliveryWithGoogle,
        calculateDistance
    };
}
