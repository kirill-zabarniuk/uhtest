SELECT p.date, SUM(p.quantity * l.price) AS price_total
FROM products p, price_log l
WHERE l.id=(
    SELECT il.id
    FROM price_log il
    WHERE il.product_id=p.product_id
      AND il.date<=p.date
    ORDER BY il.date DESC
    LIMIT 1
    )
GROUP BY p.date
ORDER BY p.date;

-- or using join syntax
SELECT p.date, SUM(p.quantity * l.price) AS price_total
FROM products p
LEFT JOIN price_log as l ON l.id=(
    SELECT il.id
    FROM price_log il
    WHERE il.product_id=p.product_id
      AND il.date<=p.date
    ORDER BY il.date DESC
    LIMIT 1
    )
GROUP BY p.date
ORDER BY p.date;
