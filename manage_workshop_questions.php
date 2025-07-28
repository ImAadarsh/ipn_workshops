<?php
// require_once 'config/show_errors.php';
require_once 'config/config.php';
require_once 'includes/head.php';

$workshop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get workshop details
$workshop_query = "SELECT name, trainer_name FROM workshops WHERE id = ? AND is_deleted = 0";
$stmt = mysqli_prepare($conn, $workshop_query);
mysqli_stmt_bind_param($stmt, "i", $workshop_id);
mysqli_stmt_execute($stmt);
$workshop = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$workshop) {
    header("Location: workshop_questions.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $question = $_POST['question_text'];
                $options = [
                    $_POST['option1'],
                    $_POST['option2'],
                    $_POST['option3'],
                    $_POST['option4']
                ];
                $correct = intval($_POST['correct_option']); // Convert to integer
                $order = $_POST['question_order'];

                $insert_query = "INSERT INTO workshop_mcq_questions 
                    (workshop_id, question_text, option1, option2, option3, option4, correct_option, question_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "isssssii", $workshop_id, $question, 
                    $options[0], $options[1], $options[2], $options[3], $correct, $order);
                mysqli_stmt_execute($stmt);
                header("Location: manage_workshop_questions.php?id=" . $workshop_id);
                exit();
                break;

            case 'edit':
                $question_id = intval($_POST['question_id']);
                $question = $_POST['question_text'];
                $option1 = $_POST['option1'];
                $option2 = $_POST['option2'];
                $option3 = $_POST['option3'];
                $option4 = $_POST['option4'];
                $correct = intval($_POST['correct_option']);
                $order = intval($_POST['question_order']);

                $update_query = "UPDATE workshop_mcq_questions 
                    SET question_text = ?, 
                        option1 = ?, 
                        option2 = ?, 
                        option3 = ?, 
                        option4 = ?, 
                        correct_option = ?, 
                        question_order = ?
                    WHERE id = ? AND workshop_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssssiiis", 
                        $question,
                        $option1,
                        $option2,
                        $option3,
                        $option4,
                        $correct,
                        $order,
                        $question_id,
                        $workshop_id
                    );
                    
                    // Debug information
                    error_log("Question ID: " . $question_id);
                    error_log("Workshop ID: " . $workshop_id);
                    error_log("Question Text: " . $question);
                    error_log("Options: " . $option1 . ", " . $option2 . ", " . $option3 . ", " . $option4);
                    error_log("Correct Option: " . $correct);
                    error_log("Order: " . $order);
                    
                    $result = mysqli_stmt_execute($stmt);
                    if ($result) {
                        header("Location: manage_workshop_questions.php?id=" . $workshop_id . "&success=1");
                    } else {
                        error_log("MySQL Error: " . mysqli_error($conn));
                        header("Location: manage_workshop_questions.php?id=" . $workshop_id . "&error=1");
                    }
                    exit();
                }
                break;

            case 'delete':
                $question_id = intval($_POST['question_id']);
                $delete_query = "DELETE FROM workshop_mcq_questions WHERE id = ? AND workshop_id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "ii", $question_id, $workshop_id);
                mysqli_stmt_execute($stmt);
                header("Location: manage_workshop_questions.php?id=" . $workshop_id);
                exit();
                break;
        }
    }
}

// Get all questions for this workshop
$questions_query = "SELECT * FROM workshop_mcq_questions WHERE workshop_id = ? ORDER BY question_order ASC";
$stmt = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($stmt, "i", $workshop_id);
mysqli_stmt_execute($stmt);
$questions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
?>

    <!-- Begin page -->
    <div class="wrapper">
        
            <!-- Sidenav Menu Start -->
            <?php include 'includes/sidenav.php'; ?>
            <!-- Sidenav Menu End -->

            <!-- Topbar Start -->
            <?php include 'includes/topbar.php'; ?>
            <!-- Topbar End -->
<!-- Start Content-->
<div class="page-content">
            <div class="page-container">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="workshop_questions.php">Workshop Questions</a></li>
                        <li class="breadcrumb-item active">Manage Questions</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    Questions for: <?php echo htmlspecialchars($workshop['name']); ?>
                    <small class="text-muted">(<?php echo htmlspecialchars($workshop['trainer_name']); ?>)</small>
                </h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Add Question Button -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="ti ti-plus me-1"></i> Add New Question
                        </button>
                    </div>

                    <!-- Questions List -->
                    <div class="table-responsive">
                        <table class="table table-centered table-striped dt-responsive nowrap w-100" id="questions-datatable">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Question</th>
                                    <th>Options</th>
                                    <th>Correct Answer</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $question): ?>
                                <tr>
                                    <td><?php echo $question['question_order']; ?></td>
                                    <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                    <td>
                                        <ol type="A">
                                            <li><?php echo htmlspecialchars($question['option1']); ?></li>
                                            <li><?php echo htmlspecialchars($question['option2']); ?></li>
                                            <li><?php echo htmlspecialchars($question['option3']); ?></li>
                                            <li><?php echo htmlspecialchars($question['option4']); ?></li>
                                        </ol>
                                    </td>
                                    <td>
                                        Option <?php echo $question['correct_option']; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-sm" 
                                                onclick="editQuestion(<?php 
                                                    echo htmlspecialchars(json_encode([
                                                        'id' => $question['id'],
                                                        'question_text' => $question['question_text'],
                                                        'option1' => $question['option1'],
                                                        'option2' => $question['option2'],
                                                        'option3' => $question['option3'],
                                                        'option4' => $question['option4'],
                                                        'correct_option' => $question['correct_option'],
                                                        'question_order' => $question['question_order']
                                                    ])); 
                                                ?>)">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="question_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option1" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option2" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option3" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option4" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Correct Option</label>
                            <select name="correct_option" class="form-control" required>
                                <option value="1">Option A</option>
                                <option value="2">Option B</option>
                                <option value="3">Option C</option>
                                <option value="4">Option D</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Question Order</label>
                            <input type="number" name="question_order" class="form-control" 
                                   value="<?php echo count($questions) + 1; ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editQuestionForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="question_text" id="edit_question_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option1" id="edit_option1" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option2" id="edit_option2" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option3" id="edit_option3" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option4" id="edit_option4" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Correct Option</label>
                            <select name="correct_option" id="edit_correct_option" class="form-control" required>
                                <option value="1">Option A</option>
                                <option value="2">Option B</option>
                                <option value="3">Option C</option>
                                <option value="4">Option D</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Question Order</label>
                            <input type="number" name="question_order" id="edit_question_order" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Question Modal -->
<div class="modal fade" id="deleteQuestionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteQuestionForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="question_id" id="delete_question_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this question? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables js -->
<script src="assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#questions-datatable').DataTable({
        responsive: true,
        order: [[0, 'asc']], // Sort by order by default
        pageLength: 25
    });
});

// Function to handle edit question
function editQuestion(data) {
    // Populate the form fields
    $('#edit_question_id').val(data.id);
    $('#edit_question_text').val(data.question_text);
    $('#edit_option1').val(data.option1);
    $('#edit_option2').val(data.option2);
    $('#edit_option3').val(data.option3);
    $('#edit_option4').val(data.option4);
    $('#edit_correct_option').val(data.correct_option);
    $('#edit_question_order').val(data.question_order);
    
    // Show the modal
    $('#editQuestionModal').modal('show');
}

// Function to handle delete question
function deleteQuestion(id) {
    $('#delete_question_id').val(id);
    $('#deleteQuestionModal').modal('show');
}

// Form submission handling
$(document).ready(function() {
    // Edit form submission
    $('#editQuestionForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = {
            action: 'edit',
            question_id: $('#edit_question_id').val(),
            question_text: $('#edit_question_text').val(),
            option1: $('#edit_option1').val(),
            option2: $('#edit_option2').val(),
            option3: $('#edit_option3').val(),
            option4: $('#edit_option4').val(),
            correct_option: $('#edit_correct_option').val(),
            question_order: $('#edit_question_order').val()
        };

        // Submit the form
        $.ajax({
            type: 'POST',
            url: window.location.href,
            data: formData,
            success: function(response) {
                window.location.reload();
            },
            error: function() {
                alert('Error updating question. Please try again.');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 