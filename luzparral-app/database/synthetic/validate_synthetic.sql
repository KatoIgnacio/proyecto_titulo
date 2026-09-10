SELECT 'users' AS entity, COUNT(*) AS quantity FROM users
UNION ALL SELECT 'communes', COUNT(*) FROM communes
UNION ALL SELECT 'feeders', COUNT(*) FROM feeders
UNION ALL SELECT 'supply_points', COUNT(*) FROM supply_points
UNION ALL SELECT 'import_batches', COUNT(*) FROM import_batches
UNION ALL SELECT 'import_errors', COUNT(*) FROM import_errors
UNION ALL SELECT 'contingencies', COUNT(*) FROM contingencies
UNION ALL SELECT 'contingency_impacts', COUNT(*) FROM contingency_impacts
UNION ALL SELECT 'contingency_history', COUNT(*) FROM contingency_history;

SELECT priority, COUNT(*) AS quantity
FROM contingencies
GROUP BY priority
ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low');

SELECT status, COUNT(*) AS quantity
FROM contingencies
GROUP BY status
ORDER BY status;

SELECT
    SUM(synthetic_code NOT LIKE 'SYN-SP-%' OR customer_code NOT LIKE 'SYN-CL-%') AS non_synthetic_codes,
    SUM(latitude NOT BETWEEN -37.0 AND -35.0 OR longitude NOT BETWEEN -72.5 AND -71.0) AS coordinates_outside_test_area
FROM supply_points;

SELECT SUM(email NOT LIKE '%@luzparral.example.invalid') AS non_synthetic_emails
FROM users;

SELECT SUM(total_rows <> accepted_rows + rejected_rows) AS inconsistent_import_batches
FROM import_batches;

SELECT COUNT(*) AS inconsistent_affected_totals
FROM contingencies AS c
JOIN (
    SELECT contingency_id, COUNT(*) AS total
    FROM contingency_impacts
    GROUP BY contingency_id
) AS i ON i.contingency_id = c.id
WHERE c.affected_total <> i.total;

SELECT COUNT(*) AS inconsistent_critical_totals
FROM contingencies AS c
JOIN (
    SELECT
        ci.contingency_id,
        SUM(sp.criticality IN ('critical', 'critical_electrodependent')) AS critical_total,
        SUM(sp.criticality IN ('electrodependent', 'critical_electrodependent')) AS electrodependent_total
    FROM contingency_impacts AS ci
    JOIN supply_points AS sp ON sp.id = ci.supply_point_id
    GROUP BY ci.contingency_id
) AS i ON i.contingency_id = c.id
WHERE c.critical_affected <> i.critical_total
   OR c.electrodependent_affected <> i.electrodependent_total;
