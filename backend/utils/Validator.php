<?php
/**
 * Validator Utility Class
 * Input validation methods
 */

class Validator {
    /**
     * Validate required fields
     */
    public static function required($value, $fieldName) {
        if (empty($value) && $value !== '0') {
            return "$fieldName is required";
        }
        return null;
    }

    /**
     * Validate email
     */
    public static function email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format";
        }
        return null;
    }

    /**
     * Validate mobile number (10 digits)
     */
    public static function mobile($mobile) {
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            return "Mobile number must be exactly 10 digits";
        }
        return null;
    }

    /**
     * Validate pincode (6 digits)
     */
    public static function pincode($pincode) {
        if (!preg_match('/^[0-9]{6}$/', $pincode)) {
            return "Pincode must be exactly 6 digits";
        }
        return null;
    }

    /**
     * Validate numeric value
     */
    public static function numeric($value, $fieldName) {
        if (!is_numeric($value)) {
            return "$fieldName must be a number";
        }
        return null;
    }

    /**
     * Validate positive number
     */
    public static function positive($value, $fieldName) {
        if (!is_numeric($value) || $value <= 0) {
            return "$fieldName must be a positive number";
        }
        return null;
    }

    /**
     * Validate string length
     */
    public static function length($value, $min, $max, $fieldName) {
        $length = strlen($value);
        if ($length < $min || $length > $max) {
            return "$fieldName must be between $min and $max characters";
        }
        return null;
    }

    /**
     * Sanitize input
     */
    public static function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)));
    }

    /**
     * Validate array of fields
     */
    public static function validateFields($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $validations) {
            $value = isset($data[$field]) ? $data[$field] : null;

            foreach ($validations as $validation) {
                $error = null;

                switch ($validation['type']) {
                    case 'required':
                        $error = self::required($value, $validation['label'] ?? $field);
                        break;
                    case 'email':
                        $error = self::email($value);
                        break;
                    case 'mobile':
                        $error = self::mobile($value);
                        break;
                    case 'pincode':
                        $error = self::pincode($value);
                        break;
                    case 'numeric':
                        $error = self::numeric($value, $validation['label'] ?? $field);
                        break;
                    case 'positive':
                        $error = self::positive($value, $validation['label'] ?? $field);
                        break;
                }

                if ($error) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        return $errors;
    }
}
?>
