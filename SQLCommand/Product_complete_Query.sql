SELECT wi.job_id,
wi.job_type,
c.customer_name, 
pl.prod_code,
       pl.prod_partno,
       wi.quantity,
       jc.prod_complete_qty, 
       jc.assembly_point,
       wi.creation_date,
       jc.date_complete
FROM wood_issue wi
LEFT JOIN prod_list pl ON pl.prod_id =  wi.prod_id
LEFT JOIN customer c ON pl.customer_id = c.customer_id
LEFT JOIN jobs_complete jc ON wi.job_id = jc.job_id
WHERE wi.issue_status = 'ปิดสำเร็จ'
  AND wi.want_receive BETWEEN STR_TO_DATE('2025-02-25', '%Y-%m-%d')
                          AND STR_TO_DATE('2025-03-17', '%Y-%m-%d');