<?php
include 'config_db.php';

if (isset($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";

    $query = "SELECT DISTINCT job_id FROM wood_issue WHERE job_id LIKE ? ORDER BY job_id ASC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $jobId = htmlspecialchars($row['job_id']);
            echo "<a href='#' class='list-group-item list-group-item-action job-suggestion-item' data-jobid='{$jobId}'>
                    {$jobId}
                  </a>";
        }
    } else {
        echo "<div class='list-group-item'>ไม่พบข้อมูล</div>";
    }

    $stmt->close();
}
$conn->close();
?>
