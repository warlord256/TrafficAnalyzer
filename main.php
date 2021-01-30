<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$msg = '';
if (isset($_POST['login']) && !empty($_POST['username'])
    && !empty($_POST['password'])) {
    $connection = oci_connect($username = $_POST['username'],
        $password = $_POST['password'],
        $connection_string = '//oracle.cise.ufl.edu/orcl');
    if ($connection != false) {
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['password'] = $_POST['password'];
        $logged_in = true;
        echo json_encode($logged_in);
        $loggedIn = true;

        //
        // VERY important to close Oracle Database Connections and free statements!
        //
        //oci_free_statement($statement);
        oci_close($connection);
    } else {
        echo 'Wrong username or password';
    }
}

function offesetLonLatByM($lon, $lat, $offsetLon, $offsetLat)
{
    $r_earth = 6371000;
    $new_latitude = ($offsetLat / $r_earth) * (180 / pi());
    $new_longitude = ($offsetLon / ($r_earth * cos(pi() * $lat / 180))) * (180 / pi());
    return ["lon" => $new_longitude, "lat" => $new_latitude];
}

function executeStatement($statementString)
{
    $connection = oci_connect($username = $_SESSION['username'],
        $password = $_SESSION['password'],
        $connection_string = '//oracle.cise.ufl.edu/orcl');
    $statement = oci_parse($connection, $statementString);
    oci_execute($statement);
    $accidents = ["rows" => []];
    while (($row = oci_fetch_object($statement))) {
        $accidents["rows"][] = $row;
    }
    oci_free_statement($statement);
    oci_close($connection);
    echo json_encode($accidents);
}

function createFilterSql($table_name_accident, $filters_post, $table_name_road)
{
    $filters = $filters_post['accident'];
    $filter_clause = '';
    foreach ($filters as $column => $filter) {
        if ($filter != 'ALL') {
            $filter_clause = $filter_clause . ' and ' . $table_name_accident . "." . $column . " ='" . $filter . "'";
        }
    }
    if (isset($filters_post['numerical'])) {
        foreach ($filters_post['numerical'] as $column => $filter) {
            if ($filter != 'ALL') {
                if ($column == 'SPEED_LIMIT') {
                    $table_name = $table_name_road;
                } else {
                    $table_name = $table_name_accident;
                }
                if ($table_name != '') {
                    if ($column == 'DAY') {
                        $filter_clause = $filter_clause . " and " . $table_name . ".DAY_OF_WEEK" . $filter;
                    } else if ($column == 'MONTH') {
                        $filter_clause = $filter_clause . ' and to_number(to_char(' . $table_name . ".ACC_DATE, 'MM'))" . $filter;
                    } else if ($column == 'YEAR') {
                        $filter_clause = $filter_clause . " and " . $table_name . ".ACC_YEAR" . $filter;
                    } else if ($column == 'HOUR') {
                        $filter_clause = $filter_clause . ' and to_number(to_char(' . $table_name . ".ACC_TIME, 'HH24'))" . $filter;
                    } else $filter_clause = $filter_clause . ' and ' . $table_name . "." . $column . $filter;
                }
            }
        }
    }
    return $filter_clause;
}

function accidentsByHourDay()
{
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('a1', $_POST['filters'], 'r1');
    $statementString = "
    SELECT DAY_OF_WEEK,ACCIDENT_HOUR,SUM(NUMBER_OF_ACCIDENTS) AS HOURLY_NUMBER_OF_ACCIDENTS FROM 
              (SELECT DAY_OF_WEEK,ACC_TIME,COUNT(*) AS NUMBER_OF_ACCIDENTS,SUBSTR( to_char(ACC_TIME, 'hh24'),1,2) AS ACCIDENT_HOUR 
              FROM VRAVI.ACCIDENT a1 JOIN VRAVI.location l1 ON a1.loccount = l1.loccount JOIN VRAVI.road_segment r1 ON a1.roadcount = r1.roadcount
              WHERE a1.ACC_TIME IS NOT NULL and l1.longitude >" . $map_bounds['lon'][0] .
            '  and l1.longitude < ' . $map_bounds['lon'][1] .
            '  and l1.latitude > ' . $map_bounds['lat'][0] .
            '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . " 
              GROUP BY DAY_OF_WEEK,ACC_TIME 
              ORDER BY DAY_OF_WEEK,ACC_TIME ASC) 
    GROUP BY DAY_OF_WEEK,ACCIDENT_HOUR ORDER BY ACCIDENT_HOUR, DAY_OF_WEEK ASC";
    executeStatement($statementString);
}

function minAccidentsInArea()
{
    $filter_clause = createFilterSql('VRAVI.accident', $_POST['filters'], 'r1');

    $map_bounds = $_POST['map_bounds'];
    $offsets = offesetLonLatByM($map_bounds['lon'][0], $map_bounds['lat'][0], $_POST['area_size'], $_POST['area_size']);
    $min_num_accidents = $_POST['min_num_accidents'];
    $offsetLon = $offsets['lon'];
    if ($offsetLon < 0) {
        $offsetLon = $offsetLon * -1;
    }
    $offsetLon_additional = 'COS(l1.latitude/' . pi() / 180 . ')';
    //echo $offsetLon_additional;
    $offsetLat = $offsets['lat'];
    if ($offsetLat < 0) {
        $offsetLat = $offsetLat * -1;
    }
    $statement_string =
        'WITH t1 AS(SELECT l1.longitude, l1.latitude, accident_index FROM VRAVI.accident, VRAVI.location l1, VRAVI.road_segment r1
                   WHERE VRAVI.accident.loccount = l1.loccount and VRAVI.accident.roadcount = r1.roadcount and l1.longitude >' . $map_bounds['lon'][0] .
                '  and l1.longitude < ' . $map_bounds['lon'][1] .
                '  and l1.latitude > ' . $map_bounds['lat'][0] .
                '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . ")
        SELECT * FROM t1 t0 
        WHERE " . $min_num_accidents . ' <=  
        (SELECT count(*)
        FROM t1 t2
         WHERE' .
        ' t2.longitude > t0.longitude -ABS(' . number_format($offsetLon, 10) .// '/' . $offsetLon_additional .
        ') and t2.longitude < t0.longitude +ABS(' . number_format($offsetLon, 10) .// '/' . $offsetLon_additional.
        ') and t2.latitude > t0.latitude -' . number_format($offsetLat, 10) .
        ' and t2.latitude < t0.latitude +' . number_format($offsetLat, 10) . ')';
    //echo $statement_string;
    executeStatement($statement_string);
}

function accidentsVersusAccidentsLastMonth()
{
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('a1', $_POST['filters'], 'r1');
    $statement_string =
        "WITH t1 AS (SELECT * 
                     FROM VRAVI.accident a1 JOIN VRAVI.road_segment r1 ON a1.roadcount = r1.roadcount JOIN VRAVI.location l1 ON a1.loccount = l1.loccount
                     WHERE l1.longitude >" . $map_bounds['lon'][0] .
                    '  and l1.longitude < ' . $map_bounds['lon'][1] .
                    '  and l1.latitude > ' . $map_bounds['lat'][0] .
                    '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . ")
        SELECT to_char(ACC_DATE, 'MM') as MONTH, to_char(ACC_DATE, 'YYYY') as YEAR, COUNT(accident_index)/NULLIF((SELECT COUNT(*) FROM t1 WHERE to_char(add_months(t1.ACC_DATE, 1), 'MM-YYYY') = to_char(t2.ACC_DATE, 'MM-YYYY')), 0) as change
        FROM t1 t2
        GROUP BY to_char(ACC_DATE, 'MM'), to_char(ACC_DATE, 'YYYY'), to_char(ACC_DATE, 'MM-YYYY'), to_number(to_char(ACC_DATE, 'MM')), to_number(to_char(ACC_DATE, 'YYYY'))
        ORDER BY to_number(to_char(ACC_DATE, 'YYYY')), to_number(to_char(ACC_DATE, 'MM'))";
    //echo $statement_string;
    executeStatement($statement_string);
}

function YearDayTotalCasualties()
{
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('accident_table', $_POST['filters'], 'r1');
    $statementString =
        "SELECT accident_table.ACC_YEAR AS ACCIDENT_YEAR, accident_table.DAY_OF_WEEK AS DAY_OF_THE_WEEK, SUM (accident_table.NUM_OF_CASUALITIES) AS TOTAL_CASUALITIES 
         FROM VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON l1.loccount = accident_table.loccount
         WHERE l1.longitude >" . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . " 
        GROUP BY 
          accident_table.ACC_YEAR, 
          accident_table.day_of_week 
        ORDER BY 
          accident_table.ACC_YEAR ASC, 
          accident_table.DAY_OF_WEEK ASC
";
    //  echo $statementString;
    executeStatement($statementString);
}

function AccidentHotspotsInAreaByTime()
{
    $filter_clause = createFilterSql('a1', $_POST['filters'], 'r1');

    $map_bounds = $_POST['map_bounds'];
    $offsets = offesetLonLatByM($map_bounds['lon'][0], $map_bounds['lat'][0], $_POST['area_size'], $_POST['area_size']);
    $min_num_accidents = $_POST['min_num_accidents'];
    $offsetLon = $offsets['lon'];
    if ($offsetLon < 0) {
        $offsetLon = $offsetLon * -1;
    }
    $offsetLon_additional = 'COS(l1.latitude/' . pi() / 180 . ')';
    //echo $offsetLon_additional;
    $offsetLat = $offsets['lat'];
    if ($offsetLat < 0) {
        $offsetLat = $offsetLat * -1;
    }
    $statement_string = '
        WITH t1 AS(
        SELECT a1.ACC_DATE, l1.longitude, l1.latitude, accident_index FROM 
        VRAVI.accident a1, VRAVI.location l1, VRAVI.road_segment r1
        WHERE a1.loccount = l1.loccount and a1.roadcount = r1.roadcount and l1.longitude >' . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . ")
        SELECT to_char(t0.ACC_DATE, 'YYYY') as year, count(*) as num_of_hotspots FROM t1 t0 WHERE " . $min_num_accidents . ' 
                                               <=  (SELECT count(*)
                                                 FROM t1 t2
                                                 WHERE' .
                                                ' t2.longitude > t0.longitude -ABS(' . number_format($offsetLon, 10) .// '/' . $offsetLon_additional .
                                                ') and t2.longitude < t0.longitude +ABS(' . number_format($offsetLon, 10) .// '/' . $offsetLon_additional.
                                                ') and t2.latitude > t0.latitude -' . number_format($offsetLat, 10) .
                                                ' and t2.latitude < t0.latitude +' . number_format($offsetLat, 10) . " and to_char(t0.ACC_DATE, 'YYYY') =  to_char(t2.ACC_DATE, 'YYYY')) GROUP BY to_char(t0.ACC_DATE, 'YYYY')";

    //echo $statement_string;
    executeStatement($statement_string);
}

function accidentsBySpeedLimit()
{
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('a', $_POST['filters'], 'r');
    $statement_string = "
        select speed_limit,count(*) as no_accidents
        from VRAVI.accident a,VRAVI.road_segment  r where a.roadcount=r.roadcount and r.longitude >" . $map_bounds['lon'][0] .
        '  and r.longitude < ' . $map_bounds['lon'][1] .
        '  and r.latitude > ' . $map_bounds['lat'][0] .
        '  and r.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        group by speed_limit
        order by count(*) desc";
    executeStatement($statement_string);
}

function monthWithHighestAccidentsByYear()
{
    $filter_clause = createFilterSql('VRAVI.accident', $_POST['filters'], 'r1');
    $map_bounds = $_POST['map_bounds'];
    $statement_string = "
    select *
    from
    (
        select 
        sum(num_of_casualities) as No_of_Casualities, 
        to_char(acc_date, 'mm') as Month, 
        to_char(acc_date, 'yyyy') as Year
        from   VRAVI.accident JOIN VRAVI.location l1 ON VRAVI.accident.loccount = l1.loccount JOIN VRAVI.road_segment r1 ON VRAVI.accident.roadcount = r1.roadcount
        WHERE  l1.longitude >" . $map_bounds['lon'][0] .
            '  and l1.longitude < ' . $map_bounds['lon'][1] .
            '  and l1.latitude > ' . $map_bounds['lat'][0] .
            '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . " 
            
        group by 
          to_char(acc_date, 'mm'), 
          to_char(acc_date, 'yyyy') 
        order by 
          sum(num_of_casualities) desc
    ) p 
    where (p.No_of_Casualities, p.Year) in (
        select max(No_of_Casualities), Year 
        from
        (
            select 
              sum(num_of_casualities) as No_of_Casualities, 
              to_char(acc_date, 'mm') as Month, 
              to_char(acc_date, 'yyyy') as Year
            from 
              VRAVI.accident JOIN VRAVI.location l1 ON VRAVI.accident.loccount = l1.loccount JOIN VRAVI.road_segment r1 ON VRAVI.accident.roadcount = r1.roadcount
             WHERE  l1.longitude >" . $map_bounds['lon'][0] .
            '  and l1.longitude < ' . $map_bounds['lon'][1] .
            '  and l1.latitude > ' . $map_bounds['lat'][0] .
            '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "  
            group by 
              to_char(acc_date, 'mm'), 
              to_char(acc_date, 'yyyy') 
            order by 
              sum(num_of_casualities) desc
        ) 
        group by Year
    ) order by p.year";
    //echo $statement_string;
    executeStatement($statement_string);
}

function conditionsCausingHighestCasualtiesMonthYear()
{
    $condition = $_POST['condition'];
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('accident_table', $_POST['filters'], 'r1');
    $statementString = "
    select  * 
    from  (
        SELECT 
          accident_table.ACC_YEAR AS ACCIDENT_YEAR, 
          to_char(acc_date, 'mm') AS MONTH, 
          SUM (
            accident_table.NUM_OF_CASUALITIES
          ) AS TOTAL_CASUALITIES, 
          accident_table." . $condition . " as CONDITION 
        FROM 
          VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
        WHERE  l1.longitude >" . $map_bounds['lon'][0] .
            '  and l1.longitude < ' . $map_bounds['lon'][1] .
            '  and l1.latitude > ' . $map_bounds['lat'][0] .
            '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        GROUP BY 
          accident_table.ACC_YEAR, 
          to_char(acc_date, 'mm'), 
          accident_table." . $condition . " 
        ORDER BY 
          accident_table.ACC_YEAR ASC, 
          to_char(acc_date, 'mm') ASC
        ) r 
    where (r.TOTAL_CASUALITIES, r.ACCIDENT_YEAR, r.MONTH) in (
        select max(TOTAL_CASUALITIES), ACCIDENT_YEAR, MONTH 
        from 
          (
            SELECT accident_table.ACC_YEAR AS ACCIDENT_YEAR, to_char(acc_date, 'mm') AS MONTH, SUM (accident_table.NUM_OF_CASUALITIES) AS TOTAL_CASUALITIES, accident_table." . $condition . " as CONDITION 
            FROM VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
             WHERE  l1.longitude >" . $map_bounds['lon'][0] .
            '  and l1.longitude < ' . $map_bounds['lon'][1] .
            '  and l1.latitude > ' . $map_bounds['lat'][0] .
            '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
            GROUP BY 
              accident_table.ACC_YEAR, 
              to_char(acc_date, 'mm'), 
              accident_table." . $condition . " 
            ORDER BY 
              accident_table.ACC_YEAR ASC, 
              to_char(acc_date, 'mm') ASC
          ) 
        group by 
          accident_year, 
          MONTH
      )";
    //echo $statementString;
    executeStatement($statementString);
}

function conditionsCausingHighestCasualtiesMonthYearOverall()
{
    $map_bounds = $_POST['map_bounds'];
    $filter_clause = createFilterSql('accident_table', $_POST['filters'], 'r1');
    $statementString = "
    WITH t1 as (
         (SELECT 
          accident_table.ACC_YEAR AS ACCIDENT_YEAR, 
          to_char(acc_date, 'mm') AS MONTH,  
          SUM (
            accident_table.NUM_OF_CASUALITIES
          ) AS TOTAL_CASUALITIES, 
          'LIGHT_CONDITIONS: '||accident_table.LIGHT_CONDITIONS as CONDITION 
        FROM 
          VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
         WHERE  l1.longitude >" . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        GROUP BY 
          accident_table.ACC_YEAR, 
          to_char(acc_date, 'mm'), 
          accident_table.LIGHT_CONDITIONS
        ) UNION
         (SELECT 
          accident_table.ACC_YEAR AS ACCIDENT_YEAR, 
          to_char(acc_date, 'mm') AS MONTH,  
          SUM (
            accident_table.NUM_OF_CASUALITIES
          ) AS TOTAL_CASUALITIES, 
           'WEATHER_CONDITIONS: '||accident_table.WEATHER_CONDITIONS as CONDITION 
        FROM 
          VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
         WHERE  l1.longitude >" . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        GROUP BY 
          accident_table.ACC_YEAR, 
          to_char(acc_date, 'mm'), 
          accident_table.WEATHER_CONDITIONS
        ) UNION
        (SELECT 
          accident_table.ACC_YEAR AS ACCIDENT_YEAR, 
          to_char(acc_date, 'mm') AS MONTH,  
          SUM (
            accident_table.NUM_OF_CASUALITIES
          ) AS TOTAL_CASUALITIES, 
           'ROAD_SURFACE_CONDITIONS: '||accident_table.ROAD_SURFACE_CONDITIONS as CONDITION 
        FROM 
          VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
         WHERE  l1.longitude >" . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        GROUP BY 
          accident_table.ACC_YEAR, 
          to_char(acc_date, 'mm'), 
          accident_table.ROAD_SURFACE_CONDITIONS
        ) UNION
        (SELECT 
          accident_table.ACC_YEAR AS ACCIDENT_YEAR, 
          to_char(acc_date, 'mm') AS MONTH,  
          SUM (
            accident_table.NUM_OF_CASUALITIES
          ) AS TOTAL_CASUALITIES, 
           'SPECIAL_CONDITIONS_AT_SITE: '||accident_table.SPECIAL_CONDITIONS_AT_SITE as CONDITION 
        FROM 
          VRAVI.ACCIDENT accident_table JOIN VRAVI.ROAD_SEGMENT r1 ON accident_table.roadcount = r1.roadcount JOIN VRAVI.location l1 ON accident_table.loccount = l1.loccount
         WHERE  l1.longitude >" . $map_bounds['lon'][0] .
        '  and l1.longitude < ' . $map_bounds['lon'][1] .
        '  and l1.latitude > ' . $map_bounds['lat'][0] .
        '  and l1.latitude < ' . $map_bounds['lat'][1] . $filter_clause . "
        GROUP BY 
          accident_table.ACC_YEAR, 
          to_char(acc_date, 'mm'), 
          accident_table.SPECIAL_CONDITIONS_AT_SITE
       ))

    SELECT 
      *
    FROM t1   
    WHERE
      (t1.TOTAL_CASUALITIES, t1.ACCIDENT_YEAR, t1.MONTH) in (
        select 
          max(TOTAL_CASUALITIES), 
          ACCIDENT_YEAR, 
          MONTH 
        from 
          t1
          WHERE CONDITION NOT LIKE '%None'
         GROUP BY t1.ACCIDENT_YEAR, t1.MONTH)
  ";
    //echo $statementString;
    executeStatement($statementString);
}


function accident_column_values($column_name)
{
    $statementString = "SELECT DISTINCT " . $column_name . " FROM VRAVI.accident";
    executeStatement($statementString);
}

function count_tuples()
{
    $statementString = "
    select sum(count) from 
       (select table_name, 
       to_number(extractvalue(xmltype(dbms_xmlgen.getxml('select count(*) c from '||owner||'.'||table_name)),'/ROWSET/ROW/C')) as count
       from all_tables
       where owner = 'VRAVI')";
    executeStatement($statementString);
}

if (isset($_POST['hm']) && isset($_POST['map_bounds']) && $_SESSION['username'] != null) {
    minAccidentsInArea();
} elseif (isset($_POST['trendMonth'])) {
    accidentsVersusAccidentsLastMonth();
} elseif (isset($_POST['column_name'])) {
    accident_column_values($_POST['column_name']);
} elseif (isset($_POST['monthWithHighestAccident'])) {
    monthWithHighestAccidentsByYear();
} elseif (isset($_POST['accidentBySpeedLimit'])) {
    accidentsBySpeedLimit();
} elseif (isset($_POST['accidentHotSpotsByTime'])) {
    AccidentHotspotsInAreaByTime();
} elseif (isset($_POST['yearDayTotalCasualties'])) {
    YearDayTotalCasualties();
} elseif (isset($_POST['conditionsCausingHighestCasualtiesMonthYear'])) {
    conditionsCausingHighestCasualtiesMonthYear();
} elseif (isset($_POST['conditionsCausingHighestCasualtiesMonthYearOverall'])) {
    conditionsCausingHighestCasualtiesMonthYearOverall();
} elseif (isset($_POST['accidentsByHourDay'])) {
    accidentsByHourDay();
} elseif (isset($_POST['countTuples'])) {
   count_tuples();
}
?>

