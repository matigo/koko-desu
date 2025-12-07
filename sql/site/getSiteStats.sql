SELECT tmp.event_on, EXTRACT(EPOCH FROM CONCAT(tmp.event_on, ' 00:00:00')::timestamp) as event_unix,
       SUM(tmp.pages) as pages, SUM(tmp.files) as files, SUM(tmp.cids) as cids,
       SUM(tmp.page_agents) as page_agents, SUM(tmp.file_agents) as file_agents
  FROM (SELECT us.event_on,
               COUNT(CASE WHEN us.is_file = false THEN us.id ELSE NULL END) as pages,
               COUNT(CASE WHEN us.is_file = true THEN us.id ELSE NULL END) as files,
               COUNT(DISTINCT CASE WHEN us.is_file = false THEN us.client_id ELSE NULL END) as cids,
               COUNT(DISTINCT CASE WHEN us.is_file = false THEN CONCAT(us.from_ip, us.agent) ELSE NULL END) as page_agents,
               COUNT(DISTINCT CASE WHEN us.is_file = true THEN CONCAT(us.from_ip, us.agent) ELSE NULL END) as file_agents
          FROM UsageStats us 
         WHERE us.is_deleted = false and us.is_api = false and us.http_code BETWEEN 200 AND 299
           and us.event_on >= (CURRENT_DATE - [DAYS])::char(10) and us.site_id = [SITE_ID]
         GROUP BY us.event_on 
         UNION ALL 
        SELECT (CURRENT_DATE - generate_series)::char(10) as event_on, 0 as pages, 0 as files, 0 as cids, 0 as page_agents, 0 as file_agents
          FROM generate_series(0, [DAYS])) tmp
 WHERE tmp.event_on >= (CURRENT_DATE - [DAYS])::char(10)
 GROUP BY tmp.event_on 
 ORDER BY tmp.event_on DESC;