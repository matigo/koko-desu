SELECT LOWER(co.code) as code, co.name, co.label
  FROM Country co
 WHERE co.is_deleted = false and co.is_available = true 
 ORDER BY co.sort_order, co.name;