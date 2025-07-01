<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการ BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
        }
        .container {
            margin-top: 50px;
        }
        .pagination {
            justify-content: center;
        }
        .icon-btn {
            border: none;
            background: none;
            cursor: pointer;
        }
        .highlighted-title {
            background-color: #25d7fd; /* สีพื้นหลังที่ต้องการ */
            padding: 10px;
            border-radius: 5px;
            color: #333; /* สีตัวหนังสือ */
            text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
            font-weight: bold;
        }

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <h2 class="text-center mb-4">รายการ BOM</h2>

    <!-- ฟอร์มสำหรับค้นหา -->
    <div class="d-flex justify-content-center mb-4">
        <input class="form-control me-2" type="search" id="search-input" placeholder="ค้นหา Product Code, ลูกค้า, หรือ ประเภทสินค้า">
    </div>

    <div id="results" class="row justify-content-center">
        <!-- ผลลัพธ์จากการค้นหาจะถูกแสดงที่นี่ -->
    </div>

    <div id="pagination" class="text-center mt-4">
        <!-- Pagination จะถูกแสดงที่นี่ -->
    </div>
</div>

<script>
    // ฟังก์ชันในการค้นหาโดยใช้ AJAX
    // ฟังก์ชันในการค้นหาโดยใช้ AJAX พร้อม Pagination
function searchBOM(searchQuery, page = 1) {
    fetch(`search_bom.php?search=${encodeURIComponent(searchQuery)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            let resultsHTML = '';
            if (data.data.length > 0) {
                data.data.forEach(row => {
                    resultsHTML += `
                        <div class="col-md-4 mb-4 product-card">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="highlighted-title">${row.prod_code}</h5>
                                    <p class="card-text">
                                        <strong>ลูกค้า:</strong> ${row.customer_name}<br>
                                        <strong>ประเภท:</strong> ${row.prod_type}<br>
                                        <strong>หมายเลขชิ้นส่วน:</strong> ${row.prod_partno}<br>
                                        <strong>รายละเอียด:</strong> ${row.prod_description}
                                    </p>
                                </div>
                                <div class="btn-group" role="group">
                                    <a href="edit_bom_parts.php?id=${row.bom_id}" class="btn btn-outline-warning btn-sm" title="แก้ไข BOM">
                                        <i class="fas fa-pencil-alt"></i> แก้ไข PART
                                    </a>
                                    <a href="edit_bom_nails.php?id=${row.bom_id}" class="btn btn-outline-success btn-sm" title="แก้ไข BOM">
                                        <i class="fas fa-pencil-alt"></i> แก้ไข ตะปู
                                    </a>
                                    <a href="delete_bom.php?id=${row.bom_id}" class="btn btn-outline-danger btn-sm" title="ลบ BOM" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบ BOM นี้?');">
                                        <i class="fas fa-trash-alt"></i> ลบ
                                    </a>
                                    <a href="bom_details.php?id=${row.bom_id}" class="btn btn-outline-info btn-sm" title="ดูรายละเอียด">
                                        <i class="fas fa-info-circle"></i> รายละเอียด
                                    </a>
                                </div>
                            </div>
                        </div>`;
                });
            } else {
                resultsHTML = '<p class="text-center">ไม่พบข้อมูล BOM</p>';
            }
            document.getElementById('results').innerHTML = resultsHTML;

            // แสดง Pagination
            let paginationHTML = '';
            if (data.total_pages > 1) {
                paginationHTML += '<nav><ul class="pagination">';
                if (page > 1) {
                    paginationHTML += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="searchBOM('${searchQuery}', ${page - 1})">Previous</a></li>`;
                }
                for (let i = 1; i <= data.total_pages; i++) {
                    paginationHTML += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="searchBOM('${searchQuery}', ${i})">${i}</a></li>`;
                }
                if (page < data.total_pages) {
                    paginationHTML += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="searchBOM('${searchQuery}', ${page + 1})">Next</a></li>`;
                }
                paginationHTML += '</ul></nav>';
            }
            document.getElementById('pagination').innerHTML = paginationHTML;
        });
}

// เรียกฟังก์ชันค้นหาทุกครั้งที่มีการพิมพ์ในช่องค้นหา
document.getElementById('search-input').addEventListener('keyup', function() {
    const searchValue = this.value;
    searchBOM(searchValue);
});

// โหลดข้อมูลเริ่มต้น
searchBOM('');

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
