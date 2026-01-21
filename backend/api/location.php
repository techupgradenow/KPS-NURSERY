<?php
/**
 * Location API
 * Handles location search, validation, and delivery area checking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Google Places API Key (Replace with your actual key)
define('GOOGLE_PLACES_API_KEY', 'YOUR_GOOGLE_PLACES_API_KEY_HERE');

try {
    switch($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Location API Error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}

/**
 * Handle GET requests
 */
function handleGet($db) {
    $action = isset($_GET['action']) ? $_GET['action'] : 'search';

    switch($action) {
        case 'search':
            searchLocation();
            break;
        case 'validate':
            validateDeliveryArea($db);
            break;
        case 'serviceable-areas':
            getServiceableAreas($db);
            break;
        default:
            Response::error('Invalid action');
    }
}

/**
 * Handle POST requests
 */
function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = isset($data['action']) ? $data['action'] : '';

    switch($action) {
        case 'add-serviceable-area':
            addServiceableArea($db, $data);
            break;
        default:
            Response::error('Invalid action');
    }
}

/**
 * Search location using Google Places Autocomplete API
 */
function searchLocation() {
    if (!isset($_GET['query']) || empty($_GET['query'])) {
        Response::error('Query parameter is required');
    }

    $query = urlencode($_GET['query']);

    // For demo purposes, return sample Bangalore locations
    // In production, use actual Google Places API
    if (GOOGLE_PLACES_API_KEY === 'YOUR_GOOGLE_PLACES_API_KEY_HERE') {
        // Return sample data for testing
        $sampleLocations = [
            [
                'place_id' => 'sample1',
                'description' => 'Koramangala, Bangalore, Karnataka, India',
                'main_text' => 'Koramangala',
                'secondary_text' => 'Bangalore, Karnataka, India',
                'lat' => 12.9352,
                'lng' => 77.6245
            ],
            [
                'place_id' => 'sample2',
                'description' => 'Indiranagar, Bangalore, Karnataka, India',
                'main_text' => 'Indiranagar',
                'secondary_text' => 'Bangalore, Karnataka, India',
                'lat' => 12.9716,
                'lng' => 77.6412
            ],
            [
                'place_id' => 'sample3',
                'description' => 'Whitefield, Bangalore, Karnataka, India',
                'main_text' => 'Whitefield',
                'secondary_text' => 'Bangalore, Karnataka, India',
                'lat' => 12.9698,
                'lng' => 77.7500
            ],
            [
                'place_id' => 'sample4',
                'description' => 'HSR Layout, Bangalore, Karnataka, India',
                'main_text' => 'HSR Layout',
                'secondary_text' => 'Bangalore, Karnataka, India',
                'lat' => 12.9121,
                'lng' => 77.6446
            ],
            [
                'place_id' => 'sample5',
                'description' => 'BTM Layout, Bangalore, Karnataka, India',
                'main_text' => 'BTM Layout',
                'secondary_text' => 'Bangalore, Karnataka, India',
                'lat' => 12.9165,
                'lng' => 77.6101
            ]
        ];

        // Filter based on query
        $query_lower = strtolower(urldecode($query));
        $results = array_filter($sampleLocations, function($loc) use ($query_lower) {
            return strpos(strtolower($loc['description']), $query_lower) !== false;
        });

        Response::success(array_values($results));
        return;
    }

    // Production code: Use Google Places API
    $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input={$query}&components=country:in&key=" . GOOGLE_PLACES_API_KEY;

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data['status'] === 'OK') {
        $predictions = array_map(function($prediction) {
            return [
                'place_id' => $prediction['place_id'],
                'description' => $prediction['description'],
                'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? ''
            ];
        }, $data['predictions']);

        Response::success($predictions);
    } else {
        Response::error('Failed to fetch locations', 400);
    }
}

/**
 * Validate if delivery is available for the given location
 */
function validateDeliveryArea($db) {
    if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
        Response::error('Latitude and longitude are required');
    }

    $lat = floatval($_GET['lat']);
    $lng = floatval($_GET['lng']);

    // Check if location is within serviceable areas
    $stmt = $db->prepare("
        SELECT *,
        (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance
        FROM serviceable_areas
        WHERE is_active = 1
        HAVING distance < radius_km
        ORDER BY distance ASC
        LIMIT 1
    ");
    $stmt->execute([$lat, $lng, $lat]);
    $area = $stmt->fetch();

    if ($area) {
        Response::success([
            'serviceable' => true,
            'area' => $area['area_name'],
            'delivery_time' => $area['delivery_time_minutes'],
            'delivery_charge' => $area['delivery_charge']
        ]);
    } else {
        Response::success([
            'serviceable' => false,
            'message' => 'Sorry, we don\'t deliver to this location yet.'
        ]);
    }
}

/**
 * Get all serviceable areas
 */
function getServiceableAreas($db) {
    $stmt = $db->prepare("SELECT * FROM serviceable_areas WHERE is_active = 1");
    $stmt->execute();
    $areas = $stmt->fetchAll();

    Response::success($areas);
}

/**
 * Add serviceable area (Admin function)
 */
function addServiceableArea($db, $data) {
    $required = ['area_name', 'lat', 'lng', 'radius_km'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            Response::error("{$field} is required");
        }
    }

    $stmt = $db->prepare("
        INSERT INTO serviceable_areas (area_name, lat, lng, radius_km, delivery_time_minutes, delivery_charge, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $data['area_name'],
        $data['lat'],
        $data['lng'],
        $data['radius_km'],
        $data['delivery_time_minutes'] ?? 30,
        $data['delivery_charge'] ?? 0
    ]);

    Response::success(['id' => $db->lastInsertId()], 'Serviceable area added successfully', 201);
}
?>
