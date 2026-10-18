<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
// Function to establish a dummy database connection (for demonstration)
function connect_db() {
    // In a real application, replace this with mysqli or PDO connection
    // $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    // if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
    // return $conn;
    return true; // Simulate successful connection
}

// Function to fetch specialization data from the database (Placeholder)
function fetch_specializations() {
    // $conn = connect_db();
    
    // PHP to run a SQL query (e.g., SELECT * FROM specializations) would go here.
    // $result = $conn->query("SELECT id, name, description, department, status, (SELECT COUNT(id) FROM doctors WHERE specialization_id = s.id) AS doctors_count FROM specializations s");
    
    // Dummy Data to simulate database output (Ensure 'id' is present for actions)
    $specializations = [
        [
            'id' => 1,
            'name' => 'Cardiology',
            'description' => 'Diagnosis and treatment of heart disorders',
            'doctors_count' => 5, // Renamed 'doctors' to 'doctors_count' for clarity
            'department' => 'Internal Medicine',
            'status' => 'Active'
        ],
        [
            'id' => 2,
            'name' => 'Neurology',
            'description' => 'Diagnosis and treatment of disorders of the nervous system',
            'doctors_count' => 3,
            'department' => 'Neuroscience',
            'status' => 'Active'
        ],
        [
            'id' => 3,
            'name' => 'Pediatrics',
            'description' => 'Medical care of infants, children, and adolescents',
            'doctors_count' => 7,
            'department' => 'Child Health',
            'status' => 'Active'
        ],
        [
            'id' => 4,
            'name' => 'Orthopedics',
            'description' => 'Treatment of the musculoskeletal system',
            'doctors_count' => 4,
            'department' => 'Surgery',
            'status' => 'Active'
        ],
        [
            'id' => 5,
            'name' => 'Dermatology',
            'description' => 'Diagnosis and treatment of skin disorders',
            'doctors_count' => 2,
            'department' => 'Skin Health',
            'status' => 'Active'
        ],
    ];
    
    // return $result->fetch_all(MYSQLI_ASSOC); // Real implementation
    return $specializations; // Dummy implementation
}

// Fetch the data
$specializations_list = fetch_specializations();

// Function to render the status badge based on its value
function render_status_badge($status) {
    $class = strtolower($status) === 'active' ? 'status-active' : 'status-inactive';
    return "<span class='status-badge $class'>" . htmlspecialchars($status) . "</span>";
}
?>

<div class="main-content">
    <div class="header-container">
        <a href="#" onclick="history.back(); return false;" class="back-arrow"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1>Specializations</h1>
            <p>Manage medical specializations in your clinic.</p>
        </div>
    </div>
    
    <div class="card specialization-manager">
        <div class="card-header-flex">
            <div>
                <h2>Specializations List</h2>
                <p>View and manage all medical specializations.</p>
            </div>
            
            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search specializations..." />
                </div>
                <button class="btn btn-primary" onclick="openModal('addSpecializationModal')">
                    <i class="fas fa-plus"></i> Add Specialization
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Doctors</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($specializations_list)): ?>
                        <?php foreach ($specializations_list as $spec): ?>
                            <tr>
                                <td><?= htmlspecialchars($spec['name']) ?></td>
                                <td><?= htmlspecialchars($spec['description']) ?></td>
                                <td><?= htmlspecialchars($spec['doctors_count']) ?></td>
                                <td><?= htmlspecialchars($spec['department']) ?></td>
                                <td><?= render_status_badge($spec['status']) ?></td>
                                <td>
                                    <div class="action-dropdown" data-id="<?= htmlspecialchars($spec['id']) ?>">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)" aria-expanded="false"><i class="fas fa-ellipsis-h"></i></button>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-header">Actions</div>
                                            <a href="edit_specialization.php?id=<?= htmlspecialchars($spec['id']) ?>"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="view_doctors.php?specialization_id=<?= htmlspecialchars($spec['id']) ?>"><i class="fas fa-user-md"></i> View doctors</a>
                                            <a href="delete_specialization.php?id=<?= htmlspecialchars($spec['id']) ?>" class="text-danger"><i class="fas fa-trash-alt"></i> Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No specializations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<style>
/* Base Styles for the Module */
.main-content {
    /* Assuming this is already defined in your global CSS */
    padding: 2rem;
    background-color: #f8f9fa;
}

.header-container {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
}

.header-container h1 {
    margin: 0 0 0.25rem 0;
    font-size: 1.75rem;
    color: #2c3e50;
}

.header-container p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.back-arrow {
    font-size: 1.5rem;
    color: #34495e;
    margin-right: 1rem;
    text-decoration: none;
    transition: color 0.2s;
}

.back-arrow:hover {
    color: #062961;
}

/* Card Styling */
.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
}

/* Card Header (for the list) */
.card-header-flex {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: 1rem;
}

.card-header-flex h2 {
    font-size: 1.25rem;
    margin: 0;
}

.card-header-flex p {
    margin: 0 1rem 0 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.card-header-flex .actions {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-left: auto;
}

/* Search Box */
.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 10px;
    color: #bdc3c7;
}

.search-box input {
    padding: 0.5rem 0.75rem 0.5rem 2.5rem;
    border: 1px solid #dfe6e9;
    border-radius: 6px;
    width: 200px;
    transition: border-color 0.2s;
}

.search-box input:focus {
    border-color: #062961;
    outline: none;
}

/* Button Styling (Assuming btn-primary is dark blue) */
.btn {
    padding: 0.65rem 1.25rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: #062961;
    color: white;
}

.btn-primary:hover {
    background-color: #002c74;
}

/* Table Styling */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.data-table th, .data-table td {
    padding: 1rem 1rem;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
    font-size: 0.9rem;
}

.data-table th {
    background-color: #f4f6f9;
    font-weight: 600;
    color: #34495e;
    text-transform: uppercase;
    font-size: 0.8rem;
}

/* Status Badges */
.status-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    display: inline-block;
}

.status-active {
    background-color: #2ecc71; /* Green */
}

.status-inactive {
    background-color: #e74c3c; /* Red/Grey */
}

/* Action Dropdown (Ellipsis) */
.action-dropdown {
    position: relative;
    display: inline-block;
}

.action-dropdown .dropdown-toggle {
    background: none;
    border: none;
    color: #7f8c8d;
    cursor: pointer;
    padding: 0.5rem;
    font-size: 1rem;
}
/* (Dropdown menu styling would require more CSS/JS for full functionality) */


/* ========================================================================= */
/* Modal Styles (Based on image_06a3aa.png) */
/* ========================================================================= */
.modal {
    display: none; /* Hidden by default */
    position: fixed; 
    z-index: 2000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4); 
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto; /* 10% from the top and centered */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    width: 90%; /* Default width */
    max-width: 600px; /* Max width for standard content */
}

.modal-content.modal-small {
    max-width: 450px; /* Width for the Add Specialization modal */
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ecf0f1;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #2c3e50;
}

.modal-description {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-top: -10px;
    margin-bottom: 20px;
}

.close-btn {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.close-btn:hover,
.close-btn:focus {
    color: #333;
    text-decoration: none;
    cursor: pointer;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #34495e;
    font-size: 0.9rem;
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #dfe6e9;
    border-radius: 6px;
    box-sizing: border-box;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #062961;
    outline: none;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #ecf0f1;
    margin-top: 1.5rem;
}

.btn-secondary {
    background-color: #ecf0ff;
    color: #34495e;
}

.btn-secondary:hover {
    background-color: #dfe6e9;
}
</style>
<script>
// Function to open the modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = "block";
}

// Function to close the modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = "none";
}

// Close the modal if the user clicks anywhere outside of it
window.onclick = function(event) {
    const addModal = document.getElementById('addSpecializationModal');
    if (event.target === addModal) {
        addModal.style.display = "none";
    }
}
</script>