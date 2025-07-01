<?php
require_once('config_db.php'); // เชื่อมต่อกับฐานข้อมูล

// สร้าง query ดึงข้อมูลจากตาราง prod_type
$query = "SELECT * FROM prod_type";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการประเภทสินค้า</title>
    <!-- เชื่อมต่อ CSS หรือ Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php include('navbar.php'); ?>
    <div class="container mt-5">
        <h2>รายการประเภทสินค้า</h2>

        <br>
        
        <!-- ตารางแสดงข้อมูลประเภทสินค้า -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>รหัสประเภทสินค้า</th>
                    <th>ชื่อประเภทสินค้า</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ตรวจสอบว่ามีข้อมูลหรือไม่
                if ($result->num_rows > 0) {
                    // แสดงข้อมูล
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['type_code']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['type_name']) . '</td>';
                        
                        // เพิ่มปุ่มแก้ไขและลบ
                        echo '<td>';
                        // ปุ่มแก้ไขเปิด Modal
                        echo '<button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" 
                                data-typeid="' . $row['type_id'] . '" 
                                data-typecode="' . htmlspecialchars($row['type_code']) . '" 
                                data-typename="' . htmlspecialchars($row['type_name']) . '">แก้ไข</button> ';
                        // ปุ่มลบ
                        echo '<a href="delete_products_type.php?type_id=' . $row['type_id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'คุณต้องการลบประเภทสินค้านี้ใช่หรือไม่?\')">ลบ</a>';
                        echo '</td>';

                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4" style="text-align: center;">ไม่พบข้อมูลประเภทสินค้า</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Modal สำหรับการแก้ไขข้อมูล -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">แก้ไขประเภทสินค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="type_id" id="type_id">
                        <div class="mb-3">
                            <label for="type_code" class="form-label">รหัสประเภทสินค้า</label>
                            <input type="text" class="form-control" id="type_code" name="type_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="type_name" class="form-label">ชื่อประเภทสินค้า</label>
                            <input type="text" class="form-control" id="type_name" name="type_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- เชื่อมต่อ JS ของ Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        // เมื่อคลิกปุ่มแก้ไขในตาราง ให้โหลดข้อมูลไปแสดงใน Modal
        var editModal = document.getElementById('editModal')
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget // ได้รับข้อมูลจากปุ่มที่คลิก
            var typeId = button.getAttribute('data-typeid')
            var typeCode = button.getAttribute('data-typecode')
            var typeName = button.getAttribute('data-typename')

            var modalBodyInput = editModal.querySelector('.modal-body #type_id')
            var modalBodyCode = editModal.querySelector('.modal-body #type_code')
            var modalBodyName = editModal.querySelector('.modal-body #type_name')

            modalBodyInput.value = typeId
            modalBodyCode.value = typeCode
            modalBodyName.value = typeName
        })

        // การส่งข้อมูลฟอร์มแก้ไข
        document.getElementById("editForm").addEventListener("submit", function (event) {
            event.preventDefault(); // ป้องกันการโหลดหน้าใหม่
            var formData = new FormData(this);
            
            // ส่งข้อมูลไปยัง server สำหรับการแก้ไข
            fetch("edit_product_type.php", {
                method: "POST",
                body: formData
            }).then(response => response.text())
              .then(data => {
                  alert(data);
                  location.reload(); // โหลดหน้าใหม่เพื่อแสดงข้อมูลล่าสุด
              }).catch(error => {
                  console.error('Error:', error);
              });
        });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
