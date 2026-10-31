
<?php
// marketplace/config/location-helper.php
// Helper functions for Nigerian States and Local Government Areas.

/**
 * Get all Nigerian states and the FCT.
 *
 * @return array List of state names.
 */
function getNigerianStates() {
    static $states = null;

    if ($states === null) {
        $statesData = require __DIR__ . '/nigeria_states.php';
        $states = is_array($statesData) ? array_keys($statesData) : [];
    }

    return $states;
}

/**
 * Get LGAs belonging to a particular state.
 *
 * @param string $state State name.
 * @return array List of LGAs.
 */
function getLGAsByState($state) {
    static $statesData = null;

    if (!is_string($state) || trim($state) === '') {
        return [];
    }

    if ($statesData === null) {
        $loadedData = require __DIR__ . '/nigeria_states.php';
        $statesData = is_array($loadedData) ? $loadedData : [];
    }

    $state = trim($state);

    return isset($statesData[$state]) && is_array($statesData[$state])
        ? $statesData[$state]
        : [];
}

/**
 * Escape a value for safe HTML output.
 *
 * @param mixed $value Value to escape.
 * @return string Escaped value.
 */
function escapeLocationHtml($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Generate State dropdown options.
 *
 * @param string $selected Currently selected state.
 * @param string $placeholder Placeholder text.
 * @return string HTML option elements.
 */
function getStateOptions($selected = '', $placeholder = 'Select State') {
    $selected = is_string($selected) ? trim($selected) : '';

    $html = '<option value="">' .
        escapeLocationHtml($placeholder) .
        '</option>';

    foreach (getNigerianStates() as $state) {
        $isSelected = ($selected === $state) ? ' selected' : '';

        $html .= '<option value="' .
            escapeLocationHtml($state) .
            '"' . $isSelected . '>' .
            escapeLocationHtml($state) .
            '</option>';
    }

    return $html;
}

/**
 * Generate LGA dropdown options for a selected state.
 *
 * @param string $state State name.
 * @param string $selected Currently selected LGA.
 * @param string $placeholder Placeholder text.
 * @return string HTML option elements.
 */
function getLGAOptions($state = '', $selected = '', $placeholder = 'Select LGA') {
    $selected = is_string($selected) ? trim($selected) : '';

    $html = '<option value="">' .
        escapeLocationHtml($placeholder) .
        '</option>';

    foreach (getLGAsByState($state) as $lga) {
        $isSelected = ($selected === $lga) ? ' selected' : '';

        $html .= '<option value="' .
            escapeLocationHtml($lga) .
            '"' . $isSelected . '>' .
            escapeLocationHtml($lga) .
            '</option>';
    }

    return $html;
}

/**
 * Format a Nigerian address.
 *
 * @param string $address Street address.
 * @param string $lga Local Government Area.
 * @param string $state State name.
 * @return string Formatted address.
 */
function formatNigerianAddress($address = '', $lga = '', $state = '') {
    $parts = [];

    foreach ([$address, $lga, $state] as $part) {
        if (is_scalar($part)) {
            $part = trim((string) $part);

            if ($part !== '') {
                $parts[] = $part;
            }
        }
    }

    return implode(', ', $parts);
}

/**
 * Validate a Nigerian state.
 *
 * @param string $state State name.
 * @return bool
 */
function isValidNigerianState($state) {
    if (!is_string($state)) {
        return false;
    }

    $state = trim($state);

    return $state !== '' &&
        in_array($state, getNigerianStates(), true);
}

/**
 * Validate an LGA against its selected state.
 *
 * @param string $state State name.
 * @param string $lga Local Government Area.
 * @return bool
 */
function isValidLGA($state, $lga) {
    if (!isValidNigerianState($state) || !is_string($lga)) {
        return false;
    }

    $lga = trim($lga);

    return $lga !== '' &&
        in_array($lga, getLGAsByState($state), true);
}

/**
 * Get all Nigerian states and their LGAs as JavaScript-safe JSON.
 *
 * @return string JSON encoded location data.
 */
function getStatesLGAsJson() {
    static $json = null;

    if ($json === null) {
        $statesData = require __DIR__ . '/nigeria_states.php';

        if (!is_array($statesData)) {
            $statesData = [];
        }

        $encoded = json_encode(
            $statesData,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        );

        $json = ($encoded !== false) ? $encoded : '{}';
    }

    return $json;
}
