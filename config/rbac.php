<?php
/**
 * Role-Based Access Control (RBAC) Helper Functions
 * Enforces access rules for Admin, Receptionist, and Cashier roles
 */

// Define role permissions
const ROLE_PERMISSIONS = [
    'Admin' => [
        'dashboard' => true,
        'patients' => true,
        'patient_create' => true,
        'patient_edit' => true,
        'patient_delete' => true,
        'patient_history' => true,
        'consultations' => true,
        'consultation_create' => true,
        'consultation_edit' => true,
        'lab_tests' => true,
        'lab_test_create' => true,
        'lab_test_add_result' => true,
        'prescriptions' => true,
        'prescription_create' => true,
        'prescription_edit' => true,
        'medicines' => true,
        'medicine_create' => true,
        'medicine_edit' => true,
        'medicine_delete' => true,
        'billing' => true,
        'billing_create' => true,
        'billing_edit' => true,
        'sales' => true,
        'reports' => true,
        'users' => true,
        'user_create' => true,
        'user_edit' => true,
        'user_delete' => true,
        'chatbot' => true,
        'messaging' => true,
    ],
    'Receptionist' => [
        'dashboard' => false,
        'patients' => true,
        'patient_create' => true,
        'patient_edit' => true,
        'patient_delete' => false,
        'patient_history' => false,
        'consultations' => false,
        'lab_tests' => false,
        'prescriptions' => false,
        'medicines' => false,
        'billing' => false,
        'sales' => false,
        'reports' => false,
        'users' => false,
        'chatbot' => false,
        'messaging' => false,
    ],
    'Cashier' => [
        'dashboard'      => true,
        'patients'       => true,
        'patient_create' => true,
        'patient_edit'   => true,
        'patient_delete' => false,
        'patient_history'=> false,
<<<<<<< HEAD
        'consultations'  => true,
=======
        'consultations'  => false,
>>>>>>> 404f1a2 (database fix)
        'lab_tests'      => true,
        'prescriptions'  => true,
        'medicines'      => true,
        'billing'        => true,
        'billing_create' => true,
        'billing_edit'   => false,
        'sales'          => true,
        'reports'        => false,
        'users'          => false,
        'chatbot'        => false,
        'messaging'      => true,
    ],
    'Lab Tech' => [
        'dashboard' => false,
        'patients' => true,
        'patient_create' => false,
        'patient_edit' => false,
        'patient_delete' => false,
        'patient_history' => false,
        'consultations' => false,
        'lab_tests' => true,
        'lab_test_create' => true,
        'lab_test_add_result' => true,
        'prescriptions' => false,
        'medicines' => false,
        'billing' => false,
        'sales' => false,
        'reports' => false,
        'users' => false,
        'chatbot' => false,
        'messaging' => false,
    ],
    'Guest' => [
        'dashboard'      => false,
        'patients'       => true,
        'patient_create' => true,
        'patient_edit'   => true,
        'patient_delete' => false,
        'patient_history'=> true,
        'consultations'  => true,
        'lab_tests'      => true,
        'prescriptions'  => true,
        'medicines'      => true,
        'billing'        => false,
        'sales'          => false,
        'reports'        => false,
        'users'          => false,
        'chatbot'        => false,
        'messaging'      => true,
    ],
];

/**
 * Check if the current user has permission to perform an action
 * 
 * @param string $action The action to check
 * @param string|null $role The user role (defaults to session role)
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($action, $role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? 'Guest';
    }
    
    if (!isset(ROLE_PERMISSIONS[$role])) {
        return false;
    }
    
    return ROLE_PERMISSIONS[$role][$action] ?? false;
}

/**
 * Check if user is Admin
 * 
 * @return bool
 */
function isAdmin() {
    return ($_SESSION['role'] ?? null) === 'Admin';
}

/**
 * Check if user is Receptionist
 * 
 * @return bool
 */
function isReceptionist() {
    return ($_SESSION['role'] ?? null) === 'Receptionist';
}

/**
 * Check if user is Cashier
 * 
 * @return bool
 */
function isCashier() {
    return ($_SESSION['role'] ?? null) === 'Cashier';
}

/**
 * Check if user is Lab Tech
 * 
 * @return bool
 */
function isLabTech() {
    return ($_SESSION['role'] ?? null) === 'Lab Tech';
}

/**
 * Check if user is Guest
 * 
 * @return bool
 */
function isGuest() {
    return ($_SESSION['role'] ?? null) === 'Guest';
}

/**
 * Check if user is Admin or Cashier
 * 
 * @return bool
 */
function isAdminOrCashier() {
    return isAdmin() || isCashier();
}

/**
 * Check if user is Admin or Receptionist
 * 
 * @return bool
 */
function isAdminOrReceptionist() {
    return isAdmin() || isReceptionist();
}

/**
 * Require a specific permission, die if not authorized
 * 
 * @param string $action The action to check
 * @param string $message Optional custom error message
 */
function requirePermission($action, $message = null) {
    if (!hasPermission($action)) {
        $default_msg = "Access Denied: You do not have permission to perform this action.";
        die('<div class="alert alert-danger">' . ($message ?? $default_msg) . '</div>');
    }
}

/**
 * Require admin role, die if not authorized
 * 
 * @param string $message Optional custom error message
 */
function requireAdmin($message = null) {
    if (!isAdmin()) {
        $default_msg = "Access Denied: Only Admins can access this resource.";
        die('<div class="alert alert-danger">' . ($message ?? $default_msg) . '</div>');
    }
}

/**
 * Require admin or cashier role, die if not authorized
 * 
 * @param string $message Optional custom error message
 */
function requireAdminOrCashier($message = null) {
    if (!isAdminOrCashier()) {
        $default_msg = "Access Denied: Only Admins and Cashiers can access this resource.";
        die('<div class="alert alert-danger">' . ($message ?? $default_msg) . '</div>');
    }
}

/**
 * Require admin or receptionist role, die if not authorized
 * 
 * @param string $message Optional custom error message
 */
function requireAdminOrReceptionist($message = null) {
    if (!isAdminOrReceptionist()) {
        $default_msg = "Access Denied: Only Admins and Receptionists can access this resource.";
        die('<div class="alert alert-danger">' . ($message ?? $default_msg) . '</div>');
    }
}

/**
 * Require lab tech or admin role, die if not authorized
 * 
 * @param string $message Optional custom error message
 */
function requireAdminOrLabTech($message = null) {
    if (!isAdmin() && !isLabTech()) {
        $default_msg = "Access Denied: Only Admins and Lab Technicians can access this resource.";
        die('<div class="alert alert-danger">' . ($message ?? $default_msg) . '</div>');
    }
}

/**
 * Get all permissions for a role
 * 
 * @param string $role The role to get permissions for
 * @return array Array of permissions
 */
function getRolePermissions($role) {
    return ROLE_PERMISSIONS[$role] ?? [];
}

/**
 * Get all roles
 * 
 * @return array Array of role names
 */
function getAllRoles() {
    return array_keys(ROLE_PERMISSIONS);
}
?>