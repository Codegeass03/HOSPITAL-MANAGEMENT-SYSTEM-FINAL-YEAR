<?php
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

if ($_POST) {
    $patient_id = $_POST['patient_id'];
    $chief_complaint = $_POST['chief_complaint'];
    $visit_type = $_POST['visit_type'];
    $current_user_id = $_SESSION['user_id']; // Assuming user session
    
    // Start new visit
    $visit_query = "
        INSERT INTO patient_visits (patient_id, visit_type, chief_complaint, triage_nurse_id, visit_status, created_by)
        VALUES (?, ?, ?, ?, 'triage', ?)
    ";
    $stmt = mysqli_prepare($link, $visit_query);
    mysqli_stmt_bind_param($stmt, "issii", $patient_id, $visit_type, $chief_complaint, $current_user_id, $current_user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $visit_id = mysqli_insert_id($link);
        header("Location: patient_triage.php?visit_id=" . $visit_id);
        exit();
    } else {
        $error = "Failed to start patient visit: " . mysqli_error($link);
    }
}
?>

<style>
.visit-form-container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #374151;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.submit-btn {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    font-size: 1rem;
}

.submit-btn:hover {
    background: #2563eb;
}

.error-message {
    background: #fef2f2;
    color: #dc2626;
    padding: 0.75rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
    border: 1px solid #fecaca;
}
</style>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                Start Patient Visit
            </h1>
            <p class="page-subtitle">
                Begin new patient consultation process
            </p>
        </div>
    </div>

    <div class="visit-form-container">
        <?php if(isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="patient_search">Search Patient</label>
                <input type="text" id="patient_search" class="form-input" placeholder="Search by patient ID, name, or phone..." onkeyup="searchPatients(this.value)">
                <div id="patient_results" style="display: none; margin-top: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; max-height: 200px; overflow-y: auto;"></div>
                <input type="hidden" id="patient_id" name="patient_id" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="visit_type">Visit Type</label>
                <select class="form-select" id="visit_type" name="visit_type" required>
                    <option value="">Select Visit Type</option>
                    <option value="consultation">Consultation</option>
                    <option value="emergency">Emergency</option>
                    <option value="followup">Follow-up</option>
                    <option value="procedure">Procedure</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="chief_complaint">Chief Complaint</label>
                <textarea class="form-textarea" id="chief_complaint" name="chief_complaint" placeholder="Enter patient's main complaint..." required></textarea>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-play-circle"></i>
                Start Visit
            </button>
        </form>
    </div>
</div>

<script>
function searchPatients(query) {
    if (query.length < 2) {
        document.getElementById('patient_results').style.display = 'none';
        return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'search_patients.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            const results = document.getElementById('patient_results');
            results.innerHTML = xhr.responseText;
            results.style.display = 'block';
        }
    };
    xhr.send('query=' + encodeURIComponent(query));
}

function selectPatient(patientId, patientName, patientNumber) {
    document.getElementById('patient_search').value = patientName + ' (' + patientNumber + ')';
    document.getElementById('patient_id').value = patientId;
    document.getElementById('patient_results').style.display = 'none';
}
</script>

<?php
include "includes/footer.php";
?>