<?php
// fetch_production_wages_summary.php

header('Content-Type: application/json'); // Set header to indicate JSON response

// ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config_db.php'; // Assume this file sets up a $conn variable as a mysqli object

$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ', 'data' => []];

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input dates for both periods
    $startDate1 = isset($_POST['startDate1']) ? $_POST['startDate1'] : null;
    $endDate1 = isset($_POST['endDate1']) ? $_POST['endDate1'] : null;
    $startDate2 = isset($_POST['startDate2']) ? $_POST['startDate2'] : null;
    $endDate2 = isset($_POST['endDate2']) ? $_POST['endDate2'] : null;

    error_log("Received dates for Period 1: " . $startDate1 . " - " . $endDate1);
    error_log("Received dates for Period 2: " . $startDate2 . " - " . $endDate2);

    // Basic date format validation (YYYY-MM-DD) for both periods
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate1) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate1) ||
        !preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate2) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate2)) {
        $response['message'] = 'รูปแบบวันที่ไม่ถูกต้อง กรุณาใช้YYYY-MM-DD สำหรับทั้งสองช่วง';
        error_log("Date format validation failed for one or both date ranges.");
        echo json_encode($response);
        exit;
    }

    // Adjust dates to cover full day (00:00:00 to 23:59:59)
    $startDate1_full = $startDate1 . ' 00:00:00';
    $endDate1_full = $endDate1 . ' 23:59:59';
    $startDate2_full = $startDate2 . ' 00:00:00';
    $endDate2_full = $endDate2 . ' 23:59:59';

    // Ensure $conn is a valid mysqli object
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $response['message'] = 'ไม่ได้เชื่อมต่อฐานข้อมูลอย่างถูกต้อง. โปรดตรวจสอบ config_db.php';
        error_log("Database connection variable \$conn is not a valid mysqli object.");
        echo json_encode($response);
        exit;
    }

    $results_period1 = [];
    $results_period2 = [];

    try {
        // Function to fetch and aggregate data for a given date range
        // This function now calculates total_m3 for each job_id including repair issues and subtracting returned m3.
        // It aggregates by assembly_point, NOT by group.
        function getAggregatedData($conn, $startDate, $endDate) {
            $aggregated_data_local = []; // Data aggregated by assembly_point
            $unique_job_ids_from_wages_local = [];
            $parsed_job_price_map_local = [];
            $job_complete_lookup_local = [];
            $total_m3_per_job_map = []; // Final aggregated m3 per job_id from all sources (net of returns)

            // Define allowed part types for M3 calculation
            $allowed_types = [
                "PINE NON FSC", "PINE NON FSC ไส", "PINE NON FSC*", "PINE NON FSC*2",
                "PINE NON FSCไส1ด้าน", "PINE NON FSC*4", "PINE NON FSCไสเหลือ 22",
                "PINE NON FSCไสเหลือ 50", "PINE NON FSC บาก", "Pine wood", "STOPPER",
                "Pine wood shocking", "PINE NON FSC เฉือน", "PINE NON FSC สโลป1แผ่น",
                "PINE NON FSC สโลป2แผ่น", "*** ไม้แพ็ค ***", "PINE NON FSC บาก2แบบ",
                "PINE NON FSC เฉือนหัว-ท้าย", "PINE NON FSC สโลป"
            ];
            
            // Helper function to pass arguments by reference for bind_param (required by mysqli)
            $ref_values = function($arr) {
                if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3+
                {
                    $refs = array();
                    foreach($arr as $key => $value)
                        $refs[$key] = &$arr[$key];
                    return $refs;
                }
                return $arr;
            };

            // --- Step 1: Fetch relevant records from production_wages for the period ---
            $sql_wages = "SELECT job_id FROM production_wages WHERE date_create BETWEEN ? AND ? AND status != 'ยกเลิก' ORDER BY date_create ASC;";
            
            $stmt_wages = $conn->prepare($sql_wages);
            if (!$stmt_wages) {
                throw new Exception("Failed to prepare SQL (wages for period): " . $conn->error);
            }
            $stmt_wages->bind_param('ss', $startDate, $endDate); // Bind full date-time strings
            if (!$stmt_wages->execute()) {
                throw new Exception("Failed to execute (wages for period): " . $stmt_wages->error);
            }
            $result_wages = $stmt_wages->get_result();
            $wage_records = $result_wages->fetch_all(MYSQLI_ASSOC);
            $stmt_wages->close();

            // --- Step 2: Parse job_id strings from production_wages and collect unique job_ids and their prices ---
            foreach ($wage_records as $wage_record) {
                $job_id_string = $wage_record['job_id'];
                if (empty($job_id_string)) {
                    continue;
                }
                
                preg_match_all('/\{([^,]+),([^}]+)\}/', $job_id_string, $matches, PREG_SET_ORDER);

                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $current_job_id = trim($match[1]);
                        $current_price_at_time = floatval(trim($match[2]));

                        if (!empty($current_job_id)) {
                            $unique_job_ids_from_wages_local[$current_job_id] = true;
                            $parsed_job_price_map_local[$current_job_id] = $current_price_at_time;
                        }
                    }
                }
            }

            // --- Step 3: Fetch jobs_complete data in a batch using collected unique job_ids ---
            if (!empty($unique_job_ids_from_wages_local)) {
                $job_ids_for_query = array_keys($unique_job_ids_from_wages_local);
                $placeholders = implode(',', array_fill(0, count($job_ids_for_query), '?'));
                $types = str_repeat('s', count($job_ids_for_query));

                $sql_jobs_complete = "SELECT job_id, prod_complete_qty, assembly_point FROM jobs_complete WHERE job_id IN ($placeholders);";
                
                $stmt_jobs_complete = $conn->prepare($sql_jobs_complete);
                if (!$stmt_jobs_complete) {
                    throw new Exception("Failed to prepare SQL (jobs_complete for period): " . $conn->error);
                }
                
                $params = array_merge([$types], $job_ids_for_query);
                call_user_func_array([$stmt_jobs_complete, 'bind_param'], $ref_values($params));

                if (!$stmt_jobs_complete->execute()) {
                    throw new Exception("Failed to execute (jobs_complete for period): " . $stmt_jobs_complete->error);
                }
                $result_jobs_complete = $stmt_jobs_complete->get_result();
                while ($row = $result_jobs_complete->fetch_assoc()) {
                    $job_complete_lookup_local[$row['job_id']] = [
                        'prod_complete_qty' => $row['prod_complete_qty'],
                        'assembly_point' => $row['assembly_point']
                    ];
                }
                $stmt_jobs_complete->close();
            }

            // --- Step 3.5: Collect ALL unique prod_ids and part_ids for M3 calculation from wood_issue, repair_issue, and return_wood_wip ---
            $all_unique_prod_ids = [];
            $all_unique_part_ids = [];
            $wood_issue_details_per_job = []; // job_id => [ {prod_id, wood_issue_qty}, ... ]
            $repair_issue_details_per_job = []; // job_id => [ {part_quantity_reason_json}, ... ]
            $m3_from_returned_wood_per_job = []; // job_id => total_returned_m3

            if (!empty($unique_job_ids_from_wages_local)) {
                $job_ids_for_m3_query = array_keys($unique_job_ids_from_wages_local);
                $placeholders_job_ids = implode(',', array_fill(0, count($job_ids_for_m3_query), '?'));
                $types_job_ids = str_repeat('s', count($job_ids_for_m3_query));

                // 3.5.1: Fetch wood_issue records for relevant job_ids
                $sql_wood_issue = "SELECT wi.job_id, wi.prod_id, wi.quantity FROM wood_issue wi WHERE wi.job_id IN ($placeholders_job_ids);";
                $stmt_wood_issue = $conn->prepare($sql_wood_issue);
                if (!$stmt_wood_issue) {
                    throw new Exception("Failed to prepare SQL (wood_issue for M3): " . $conn->error);
                }
                $params_wood_issue = array_merge([$types_job_ids], $job_ids_for_m3_query);
                call_user_func_array([$stmt_wood_issue, 'bind_param'], $ref_values($params_wood_issue));
                if (!$stmt_wood_issue->execute()) {
                    throw new Exception("Failed to execute (wood_issue for M3): " . $stmt_wood_issue->error);
                }
                $result_wood_issue = $stmt_wood_issue->get_result();
                while ($row = $result_wood_issue->fetch_assoc()) {
                    $wood_issue_details_per_job[$row['job_id']][] = [
                        'prod_id' => $row['prod_id'],
                        'wood_issue_qty' => $row['quantity']
                    ];
                    $all_unique_prod_ids[trim($row['prod_id'])] = true;
                }
                $stmt_wood_issue->close();

                // 3.5.2: Fetch repair_issue records for relevant job_ids
                $sql_repair_issue = "SELECT ri.job_id, ri.part_quantity_reason FROM repair_issue ri WHERE ri.job_id IN ($placeholders_job_ids);";
                $stmt_repair_issue = $conn->prepare($sql_repair_issue);
                if (!$stmt_repair_issue) {
                    throw new Exception("Failed to prepare SQL (repair_issue for M3): " . $conn->error);
                }
                $params_repair_issue = array_merge([$types_job_ids], $job_ids_for_m3_query);
                call_user_func_array([$stmt_repair_issue, 'bind_param'], $ref_values($params_repair_issue));
                if (!$stmt_repair_issue->execute()) {
                    throw new Exception("Failed to execute (repair_issue for M3): " . $stmt_repair_issue->error);
                }
                $result_repair_issue = $stmt_repair_issue->get_result();
                while ($row = $result_repair_issue->fetch_assoc()) {
                    $repair_issue_details_per_job[$row['job_id']][] = $row['part_quantity_reason'];
                }
                $stmt_repair_issue->close();

                // 3.5.3: Fetch return_wood_wip data for relevant job_ids
                $sql_return_wood = "
                    SELECT
                        job_id,
                        SUM(return_total_m3) AS total_returned_m3
                    FROM
                        return_wood_wip
                    WHERE
                        job_id IN ($placeholders_job_ids)
                    GROUP BY
                        job_id;
                ";
                $stmt_return_wood = $conn->prepare($sql_return_wood);
                if (!$stmt_return_wood) {
                    throw new Exception("Failed to prepare SQL (return_wood_wip for M3): " . $conn->error);
                }
                $params_return_wood = array_merge([$types_job_ids], $job_ids_for_m3_query);
                call_user_func_array([$stmt_return_wood, 'bind_param'], $ref_values($params_return_wood));
                if (!$stmt_return_wood->execute()) {
                    throw new Exception("Failed to execute (return_wood_wip for M3): " . $stmt_return_wood->error);
                }
                $result_return_wood = $stmt_return_wood->get_result();
                while ($row = $result_return_wood->fetch_assoc()) {
                    $m3_from_returned_wood_per_job[$row['job_id']] = floatval($row['total_returned_m3']);
                }
                $stmt_return_wood->close();
            }

            // 3.5.4: Fetch bom.parts for all collected prod_ids
            $bom_parts_lookup = []; // prod_id => json_string of bom.parts
            $parsed_bom_parts_map = []; // prod_id => [ {part_id, quantity_in_bom}, ... ]

            if (!empty($all_unique_prod_ids)) {
                $prod_ids_for_bom_query = array_keys($all_unique_prod_ids);
                $placeholders_prod_ids = implode(',', array_fill(0, count($prod_ids_for_bom_query), '?'));
                $types_prod_ids = str_repeat('s', count($prod_ids_for_bom_query));

                $sql_bom = "SELECT bom.prod_id, bom.parts FROM bom WHERE bom.prod_id IN ($placeholders_prod_ids);";
                $stmt_bom = $conn->prepare($sql_bom);
                if (!$stmt_bom) {
                    throw new Exception("Failed to prepare SQL (BOM for M3): " . $conn->error);
                }
                $params_bom = array_merge([$types_prod_ids], $prod_ids_for_bom_query);
                call_user_func_array([$stmt_bom, 'bind_param'], $ref_values($params_bom));
                if (!$stmt_bom->execute()) {
                    throw new Exception("Failed to execute (BOM for M3): " . $stmt_bom->error);
                }
                $result_bom = $stmt_bom->get_result();
                while ($row = $result_bom->fetch_assoc()) {
                    $bom_parts_lookup[$row['prod_id']] = $row['parts'];
                    $parts_array = json_decode($row['parts'], true);
                    if (is_array($parts_array)) {
                        $parsed_bom_parts_map[$row['prod_id']] = $parts_array;
                        foreach ($parts_array as $part) {
                            if (isset($part['part_id'])) {
                                $all_unique_part_ids[trim($part['part_id'])] = true;
                            }
                        }
                    }
                }
                $stmt_bom->close();
            }

            // 3.5.5: Parse repair_issue.part_quantity_reason and collect unique part_ids
            foreach ($repair_issue_details_per_job as $job_id_key => $repair_jsons) {
                foreach ($repair_jsons as $json_string) {
                    $parts_array = json_decode($json_string, true);
                    if (is_array($parts_array)) {
                        foreach ($parts_array as $part) {
                            if (isset($part['part_id'])) {
                                $all_unique_part_ids[trim($part['part_id'])] = true;
                            }
                        }
                    }
                }
            }

            // 3.5.6: Get part_list.part_m3 and part_list.part_type for ALL collected unique part_ids, filtered by allowed types
            $part_m3_lookup = [];
            if (!empty($all_unique_part_ids)) {
                $part_ids_for_query = array_keys($all_unique_part_ids);
                $placeholders_parts = implode(',', array_fill(0, count($part_ids_for_query), '?'));
                $types_parts = str_repeat('s', count($part_ids_for_query));

                // Add filtering for part_type here
                $placeholders_allowed_types = implode(',', array_fill(0, count($allowed_types), '?'));
                $types_allowed_types = str_repeat('s', count($allowed_types));

                $sql_part_m3 = "SELECT pl.part_id, pl.part_m3 FROM part_list pl WHERE pl.part_id IN ($placeholders_parts) AND pl.part_type IN ($placeholders_allowed_types);";
                $stmt_part_m3 = $conn->prepare($sql_part_m3);
                if (!$stmt_part_m3) {
                    throw new Exception("Failed to prepare SQL (part_list for M3 filtered): " . $conn->error);
                }

                // Combine parameters for bind_param: first part_ids, then allowed_types
                $all_bind_params = array_merge([$types_parts . $types_allowed_types], $part_ids_for_query, $allowed_types);
                call_user_func_array([$stmt_part_m3, 'bind_param'], $ref_values($all_bind_params));

                if (!$stmt_part_m3->execute()) {
                    throw new Exception("Failed to execute (part_list for M3 filtered): " . $stmt_part_m3->error);
                }
                $result_part_m3 = $stmt_part_m3->get_result();
                while ($row = $result_part_m3->fetch_assoc()) {
                    $part_m3_lookup[$row['part_id']] = floatval($row['part_m3']);
                }
                $stmt_part_m3->close();
            }

            // 3.5.7: Calculate total M3 from wood_issue per job_id
            $m3_from_wood_issue_per_job = [];
            foreach ($wood_issue_details_per_job as $job_id_key => $issues_for_job) {
                $total_m3_for_this_job_wood = 0.0;
                foreach ($issues_for_job as $issue_detail) {
                    $wi_prod_id = $issue_detail['prod_id'];
                    $wi_quantity = intval($issue_detail['wood_issue_qty']);

                    $m3_for_one_product_unit = 0.0;
                    if (isset($parsed_bom_parts_map[$wi_prod_id])) {
                        foreach ($parsed_bom_parts_map[$wi_prod_id] as $bom_part) {
                            $part_id_in_bom = trim($bom_part['part_id']);
                            $quantity_in_bom = intval($bom_part['quantity']);
                            if (isset($part_m3_lookup[$part_id_in_bom])) {
                                $m3_for_one_product_unit += ($quantity_in_bom * $part_m3_lookup[$part_id_in_bom]);
                            } else {
                                // Log missing part_id in lookup (if it's not in allowed types, it won't be in lookup)
                                error_log("part_id " . $part_id_in_bom . " from BOM for prod_id " . $wi_prod_id . " not found in part_list_m3_lookup (wood_issue context), possibly due to type filter.");
                            }
                        }
                    } else {
                         error_log("prod_id " . $wi_prod_id . " from wood_issue not found in parsed_bom_parts_map.");
                    }
                    $total_m3_for_this_job_wood += ($wi_quantity * $m3_for_one_product_unit);
                }
                $m3_from_wood_issue_per_job[$job_id_key] = $total_m3_for_this_job_wood;
            }

            // 3.5.8: Calculate total M3 from repair_issue per job_id
            $m3_from_repair_issue_per_job = [];
            foreach ($repair_issue_details_per_job as $job_id_key => $repair_jsons) {
                $total_m3_for_this_job_repair = 0.0;
                foreach ($repair_jsons as $json_string) {
                    $parts_array = json_decode($json_string, true);
                    if (is_array($parts_array)) {
                        foreach ($parts_array as $part) {
                            $part_id_in_repair = trim($part['part_id']);
                            $quantity_in_repair = intval($part['quantity']);
                            if (isset($part_m3_lookup[$part_id_in_repair])) {
                                $total_m3_for_this_job_repair += ($quantity_in_repair * $part_m3_lookup[$part_id_in_repair]);
                            } else {
                                // Log missing part_id in lookup (if it's not in allowed types, it won't be in lookup)
                                error_log("part_id " . $part_id_in_repair . " from repair_issue for job_id " . $job_id_key . " not found in part_list_m3_lookup (repair_issue context), possibly due to type filter.");
                            }
                        }
                    } else {
                        error_log("Failed to decode JSON from repair_issue.part_quantity_reason: " . $json_string);
                    }
                }
                $m3_from_repair_issue_per_job[$job_id_key] = $total_m3_for_this_job_repair;
            }

            // 3.5.9: Combine M3 from wood_issue and repair_issue, then subtract returned M3 for each job_id
            foreach ($unique_job_ids_from_wages_local as $job_id_key => $value) {
                $m3_from_wood = isset($m3_from_wood_issue_per_job[$job_id_key]) ? $m3_from_wood_issue_per_job[$job_id_key] : 0.0;
                $m3_from_repair = isset($m3_from_repair_issue_per_job[$job_id_key]) ? $m3_from_repair_issue_per_job[$job_id_key] : 0.0;
                $m3_returned = isset($m3_from_returned_wood_per_job[$job_id_key]) ? $m3_from_returned_wood_per_job[$job_id_key] : 0.0;

                // Net M3 calculation
                $total_m3_per_job_map[$job_id_key] = ($m3_from_wood + $m3_from_repair) - $m3_returned;
            }

            // --- Step 4: Calculate and aggregate results by assembly_point, including M3 ---
            // Removed $get_group_name here. Aggregation is by original assembly_point.
            foreach ($parsed_job_price_map_local as $job_id => $price_at_time) {
                if (isset($job_complete_lookup_local[$job_id])) {
                    $jc_data = $job_complete_lookup_local[$job_id];
                    $prod_complete_qty = intval($jc_data['prod_complete_qty']);
                    $assembly_point = $jc_data['assembly_point'];
                    
                    // Get combined and net M3 for this job_id, default to 0 if not found
                    $total_m3_for_job = isset($total_m3_per_job_map[$job_id]) ? $total_m3_per_job_map[$job_id] : 0.0;

                    $calculated_total_price = $prod_complete_qty * $price_at_time;

                    // Aggregate by original assembly_point
                    if (!isset($aggregated_data_local[$assembly_point])) {
                        $aggregated_data_local[$assembly_point] = [
                            'assembly_point' => $assembly_point, // Store the original assembly point name
                            'total_quantity' => 0,
                            'total_m3' => 0.0, // Initialize total M3
                            'total_price' => 0.0
                        ];
                    }
                    $aggregated_data_local[$assembly_point]['total_quantity'] += $prod_complete_qty;
                    $aggregated_data_local[$assembly_point]['total_m3'] += $total_m3_for_job; // Add NET M3 to aggregation
                    $aggregated_data_local[$assembly_point]['total_price'] += $calculated_total_price;
                }
            }
            return $aggregated_data_local;
        }

        // Fetch data for Period 1
        $results_period1 = getAggregatedData($conn, $startDate1_full, $endDate1_full);
        error_log("Aggregated data for Period 1: " . json_encode($results_period1));

        // Fetch data for Period 2
        $results_period2 = getAggregatedData($conn, $startDate2_full, $endDate2_full);
        error_log("Aggregated data for Period 2: " . json_encode($results_period2));

        // Prepare response data for comparison
        $response['status'] = 'success';
        $response['message'] = 'Summary data loaded successfully.';
        $response['data'] = [
            'period1_data' => $results_period1, // Send associative array indexed by assembly_point
            'period2_data' => $results_period2  // Send associative array indexed by assembly_point
        ];

    } catch (mysqli_sql_exception $e) {
        error_log("Database Error in fetch_production_wages_summary.php: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        error_log("General Error in fetch_production_wages_summary.php: " . $e->getMessage());
        $response['message'] = 'General error: ' . $e->getMessage();
    } finally {
        // No explicit $conn->close() here; assume connection management is outside this script for global scope.
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
}

echo json_encode($response);
