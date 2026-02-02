<?php
// as/api/upload_csv.php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Unauthorized"]));
}

$target_dir = "../../"; // Parent directory where CSVs live
$target_file = $target_dir . basename($_FILES["csv_file"]["name"]);
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Check if file is a CSV
if($imageFileType != "csv") {
    die(json_encode(["status" => "error", "message" => "Only CSV files are allowed."]));
}

if (move_uploaded_file($_FILES["csv_file"]["tmp_name"], $target_file)) {
    echo json_encode(["status" => "success", "message" => "The file ". htmlspecialchars(basename($_FILES["csv_file"]["name"])). " has been uploaded."]);
} else {
    echo json_encode(["status" => "error", "message" => "Sorry, there was an error uploading your file."]);
}
?>
