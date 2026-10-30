<?php
// marketplace/config/location-helper.php
// Helper functions for Nigerian States and LGAs

/**
 * Get all Nigerian states
 * 
 * @return array List of state names
 */
function getNigerianStates() {
    static $states = null;
    if ($states === null) {
        $statesData = require __DIR__ . '/nigeria_states.php';
        $states = array_keys($statesData);
    }
    return $states;
}

/**
 * Get LGAs for a given state
 * 
 * @param string $state State name
 * @return array List of LGAs
 */
function getLGAsByState($state) {
    static $statesData = null;
    if ($statesData === null) {
        $statesData = require __DIR__ . '/nigeria_states.php';
    }
    return $statesData[$state] ?? [];
}

/**
 * Generate a State dropdown
 * 
 * @param string $name Field name
 * @param string $selected Currently selected value
 * @param string $class CSS classes
 * @param bool $required Whether field is required
 * @return string HTML select options
 */
function getStateOptions($selected = '', $placeholder = 'Select State') {
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    foreach (getNigerianStates() as $state) {
        $isSelected = ($selected === $state) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($state) . '" ' . $isSelected . '>' . htmlspecialchars($state) . '</option>';
    }
    return $html;
}

/**
 * Generate an LGA dropdown based on state
 * 
 * @param string $state State name
 * @param string $selected Currently selected LGA
 * @param string $placeholder Placeholder text
 * @return string HTML select options
 */
function getLGAOptions($state = '', $selected = '', $placeholder = 'Select LGA') {
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    if (!empty($state)) {
        foreach (getLGAsByState($state) as $lga) {
            $isSelected = ($selected === $lga) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($lga) . '" ' . $isSelected . '>' . htmlspecialchars($lga) . '</option>';
        }
    }
    return $html;
}

/**
 * Format a full Nigerian address
 * 
 * @param string $address Street address
 * @param string $lga LGA
 * @param string $state State
 * @return string Formatted address
 */
function formatNigerianAddress($address = '', $lga = '', $state = '') {
    $parts = array_filter([$address, $lga, $state]);
    return implode(', ', $parts);
}

/**
 * Validate a Nigerian state
 * 
 * @param string $state State name
 * @return bool
 */
function isValidNigerianState($state) {
    return in_array($state, getNigerianStates());
}

/**
 * Validate an LGA for a given state
 * 
 * @param string $state State name
 * @param string $lga LGA name
 * @return bool
 */
function isValidLGA($state, $lga) {
    if (!isValidNigerianState($state)) return false;
    return in_array($lga, getLGAsByState($state));
}

/**
 * Get all states with their LGAs as JSON (for JavaScript)
 * 
 * @return string JSON encoded data
 */
function getStatesLGAsJson() {
    static $statesData = null;
    if ($statesData === null) {
        $statesData = require __DIR__ . '/nigeria_states.php';
    }
    return json_encode($statesData);
}
?>