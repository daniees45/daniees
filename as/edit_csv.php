<?php
// as/edit_csv.php
$page_title = 'Edit CSV Data';
include 'includes/header.php';

$file = $_GET['file'] ?? '';
// Validate Filename Security
if (!$file || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'csv') {
    echo '<div class="alert alert-danger">Invalid file specified. Only CSV files are allowed.</div>';
    include 'includes/footer.php';
    exit;
}

// Verify file exists in parent directory to prevent traversal
$file_path = realpath('../' . $file);
$base_dir = realpath('../');

if (!$file_path || strpos($file_path, $base_dir) !== 0 || !file_exists($file_path)) {
    echo '<div class="alert alert-danger">File not found or access denied.</div>';
    include 'includes/footer.php';
    exit;
}

$file_path = '../' . $file;
$rows = [];
if (file_exists($file_path)) {
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
} else {
    echo '<div class="alert alert-danger">File not found.</div>';
    include 'includes/footer.php';
    exit;
}

$headers = array_shift($rows);
?>

<div class="glass-panel" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2><i class="fa-solid fa-edit"></i> Editing: <?php echo htmlspecialchars($file); ?></h2>
        <div>
            <button onclick="addRow()" class="glass-btn secondary"><i class="fa-solid fa-plus"></i> Add Row</button>
            <button onclick="saveChanges()" class="glass-btn"><i class="fa-solid fa-save"></i> Save Changes</button>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table" id="csvTable">
            <thead>
                <tr>
                    <?php foreach ($headers as $h): ?>
                        <th><?php echo htmlspecialchars($h); ?></th>
                    <?php endforeach; ?>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $rowIndex => $row): ?>
                    <tr>
                        <?php foreach ($row as $cellIndex => $cell): ?>
                            <td contenteditable="true"><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <button class="text-btn danger" onclick="deleteRow(this)"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addRow() {
    const table = document.getElementById('csvTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    const colCount = <?php echo count($headers); ?>;
    
    for (let i = 0; i < colCount; i++) {
        const cell = newRow.insertCell(i);
        cell.contentEditable = "true";
        cell.innerText = "";
    }
    
    const actionCell = newRow.insertCell(colCount);
    actionCell.innerHTML = '<button class="text-btn danger" onclick="deleteRow(this)"><i class="fa-solid fa-trash"></i></button>';
}

async function deleteRow(btn) {
    if (await customConfirm('Delete Row', 'Are you sure you want to delete this row?')) {
        const row = btn.parentNode.parentNode;
        row.parentNode.removeChild(row);
    }
}

async function saveChanges() {
    const table = document.getElementById('csvTable');
    const rows = table.querySelectorAll('tbody tr');
    const headers = <?php echo json_encode($headers); ?>;
    const data = [headers];
    
    rows.forEach(tr => {
        const rowData = [];
        const cells = tr.querySelectorAll('td');
        for (let i = 0; i < headers.length; i++) {
            rowData.push(cells[i].innerText.trim());
        }
        data.push(rowData);
    });

    const fileName = '<?php echo $file; ?>';
    
    try {
        const response = await fetch('api/save_csv.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: fileName, data: data })
        });
        
        const result = await response.json();
        if (result.status === 'success') {
            await customAlert('Success', result.message, 'success');
        } else {
            await customAlert('Save Error', result.message, 'error');
        }
    } catch (error) {
        await customAlert('System Error', 'Failed to save: ' + error.message, 'error');
    }
}
</script>



<?php include 'includes/footer.php'; ?>
