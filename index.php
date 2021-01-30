<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.3.0/css/ol.css"
      type="text/css">

<link rel="stylesheet" type="text/css" href="main.css">
<script src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.3.0/build/ol.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="./Chart.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/ol-geocoder@latest/dist/ol-geocoder.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/ol-geocoder"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/chartjs-plugin-colorschemes"></script>
<div id="loginformcontainer" class="container">
    <form id="formoid" class="form-signin" role="form"
          action="main.php" method="post">
        <h4 class="form-signin-heading">Enter Oracle DB credentials</h4>
        <input type="text" class="form-control"
               name="username"
               required autofocus></br>
        <input type="password" class="form-control"
               name="password" required>
        <button class="btn btn-lg btn-primary btn-block" type="submit"
                name="login">Login
        </button>
    </form>
</div>
<script type='text/javascript'>

    /* attach a submit handler to the form */
    $("#formoid").submit(function (event) {

        /* stop form from submitting normally */
        event.preventDefault();

        /* get the action attribute from the <form action=""> element */
        var $form = $(this),
            url = $form.attr('action');
        var data = $form.serializeArray().reduce(function (obj, item) {
            obj[item.name] = item.value;
            return obj;
        }, {});
        data['login'] = true;
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: data,
            success: function (data2) {
                console.log(JSON.parse(data2));
                if (JSON.parse(data2) === true) {
                    document.getElementById('loginformcontainer').style.display = "none"
                } else {

                }
            }
        });
    });
</script>
<div id="map" class="map"></div>
<select id="units">
    <option value="degrees">degrees</option>
    <option value="imperial">imperial inch</option>
    <option value="us">us inch</option>
    <option value="nautical">nautical mile</option>
    <option value="metric">metric</option>
</select>
<script defer type="text/javascript">
    my_Chart = null;
    var scaleLineControl = new ol.control.ScaleLine();
    var map = new ol.Map({
        target: 'map',
        controls: ol.control.defaults({
            attributionOptions: /** @type {olx.control.AttributionOptions} */ ({
                collapsible: false
            })
        }).extend([
            scaleLineControl
        ]),
        layers: [
            new ol.layer.Tile({
                source: new ol.source.OSM()
            })
        ],
        view: new ol.View({
            center: ol.proj.fromLonLat([-0.118092, 51.509865]),// LONDON
            zoom: 4
        })
    });

    var unitsSelect = $('#units');
    unitsSelect.on('change', function () {
        scaleLineControl.setUnits(this.value);
    });
    unitsSelect.val(scaleLineControl.getUnits());

    var geocoder = new Geocoder('nominatim', {
        provider: 'osm',
        lang: 'en',
        placeholder: 'Search for ...',
        limit: 5,
        debug: false,
        autoComplete: true,
        keepOpen: true
    });
    map.addControl(geocoder);

    geocoder.on('addresschosen', function (evt) {
        var feature = evt.feature,
            coord = evt.coordinate,
            address = evt.address;
        // some popup solution
        content.innerHTML = '<p>' + address.formatted + '</p>';
        overlay.setPosition(coord);
    });

</script>
<script>
    function getFilterColumns() {
        let filter_columns_accident = ['LIGHT_CONDITIONS', 'WEATHER_CONDITIONS'
            , 'ROAD_SURFACE_CONDITIONS', 'SPECIAL_CONDITIONS_AT_SITE', 'CARRIAGEWAY_HAZARDS', 'DID_POLICE_OFFICER_ATTEND_SCENE_OF_ACCIDENT'];
        let filter_numerical_columns = ['NUM_OF_VEHICLES', 'NUM_OF_CASUALITIES', 'SPEED_LIMIT', 'DAY', 'HOUR', 'MONTH', 'YEAR'];
        let filters = {accident: {}, numerical: {}};
        for (let column of filter_columns_accident) {
            filters['accident'][column] = document.getElementById('filter_' + column).value;
        }
        for (let column of filter_numerical_columns) {
            filters['numerical'][column] = document.getElementById('filter_' + column).value + document.getElementById('filter_input_' + column).value;
            if(filters['numerical'][column] === '>=0') delete filters['numerical'][column];
        }
        return filters;
    }
</script>
<div id="buttonsDiv">
    <div>
        <button onclick="buttonPressed()">Heatmap of Hotspots</button>
        <label for="inputAreaSize">Size of Area around Accident Hotspot (in m):</label>
        <input id="inputAreaSize" value=0 type="number">
    </div>
    <div>
        <button onclick="accidentHotspotsByTime()">Number of Accident Hotspots by Year</button>
        <label for="inputNumOfAccidentsInArea">Minimum number of accidents in Hotspot</label>
        <input id="inputNumOfAccidentsInArea" value=0 type="number">
    </div>
    <div>
        <button onclick="chartTrendMonth()">Monthly Change of Accident Numbers</button>
    </div>
    <div>
        <button onclick="monthWithHighestAccident()">Month with Highest Casualties by Year</button>
    </div>
    <div>
        <button onclick="accidentBySpeedLimit()">Number of Accidents by Speed Limit</button>
    </div>
    <div>
        <button onclick="yearDayTotalCasualties()">Total Casualties by Day and Year</button>
    </div>
    <div>
        <button onclick="conditionsCausingHighestCasualtiesMonthYear()">Conditions Causing Highest Casualties by Month
            and Year
        </button>
        <select class="js - 2select - dropbox" id='conditionCheck' name="state">
            <option value='LIGHT_CONDITIONS'>LIGHT_CONDITIONS</option>
            <option value='WEATHER_CONDITIONS'>WEATHER_CONDITIONS</option>
            <option value='SPECIAL_CONDITIONS_AT_SITE'>SPECIAL_CONDITIONS_AT_SITE</option>
            <option value='ROAD_SURFACE_CONDITIONS'>ROAD_SURFACE_CONDITIONS</option>

        </select>

        <script>
            $('#conditionCheck').select2({
                width: 300
                //dropdownAutoWidth: true
            });
        </script>
    </div>
    <div>
        <button onclick="overallConditionsCausingHighestCasualtiesMonthYear()">Overall Conditions Causing Highest
            Casualties by Month and Year
        </button>
    </div>
    <div>
        <button onclick="accidentsByHourDay()">Accident Numbers by Day and Hour</button>
    </div>
    <div>
        <button onclick="countTuples()">Count Tuples</button>
    </div>


    <script type="text/javascript">
        function getCurrentMapBounds() {
            var bounds = map.getView().calculateExtent(map.getSize());
            var bottom_left = ol.proj.toLonLat([bounds[0], bounds[1]]);
            var top_right = ol.proj.toLonLat([bounds[2], bounds[3]]);
            return {lon: [bottom_left[0], top_right[0]], lat: [bottom_left[1], top_right[1]]};
        }

        function buttonPressed() {
            var boundsInLonLat = getCurrentMapBounds();

            $.ajax({
                type: "POST",
                url: "./main.php",
                datatype: "json",
                data: {
                    hm: true,
                    map_bounds: boundsInLonLat,
                    area_size: document.getElementById('inputAreaSize').value,
                    min_num_accidents: document.getElementById('inputNumOfAccidentsInArea').value,
                    filters: getFilterColumns()
                },
                success: function (data2) {
                    console.log(data2);
                    data2 = JSON.parse(data2);
                    data_hm = new ol.source.Vector();
                    for (row of data2['rows']) {
                        var coord = ol.proj.fromLonLat([parseFloat(row["LONGITUDE"]), parseFloat(row["LATITUDE"])]);  // Barcelona, Spain
                        var lonLat = new ol.geom.Point(coord);
                        var pointFeature = new ol.Feature({
                            geometry: lonLat,
                            weight: 20 // e.g. temperature
                        });
                        data_hm.addFeature(pointFeature);
                    }

// create the layer
                    console.log(map.getLayers().getArray()
                        .filter(layer => layer.get('name') === 'heatmap'))

                    map.getLayers().getArray()
                        .filter(layer => layer.get('name') === 'heatmap')
                        .forEach(layer => map.removeLayer(layer));
                    console.log(map.getLayers().getArray()
                        .filter(layer => layer.get('name') === 'heatmap'))
                    heatMapLayer = new ol.layer.Heatmap({
                        name: 'heatmap',
                        source: data_hm,
                        radius: 5
                    });


// add to the map
                    map.addLayer(heatMapLayer);
                }
            });
        }
    </script>
    <script>

        function createDropdownAddFunction(column_name) {
            return function (data2) {
                console.log(data2);
                data2 = JSON.parse(data2);
                for (row of data2['rows']) {
                    var opt = document.createElement('option');
                    if (row[column_name]) {
                        opt.value = row[column_name];
                        opt.innerHTML = row[column_name];
                        document.getElementById('filter_' + column_name).appendChild(opt);
                    }
                }
                $('#filter_' + column_name).select2({
                    width: 200
                    //dropdownAutoWidth: true
                });
            };
        }
    </script>
    <div class="container">
        <div class="header">
            <button>Expand Filters</button>

        </div>
        <div class="content">
            <?php
            $filter_columns_accident = ['LIGHT_CONDITIONS', 'WEATHER_CONDITIONS'
                , 'ROAD_SURFACE_CONDITIONS', 'SPECIAL_CONDITIONS_AT_SITE', 'CARRIAGEWAY_HAZARDS', 'DID_POLICE_OFFICER_ATTEND_SCENE_OF_ACCIDENT'];
            $numerical_columns = ['NUM_OF_VEHICLES', 'NUM_OF_CASUALITIES', 'SPEED_LIMIT','MONTH', 'YEAR', 'DAY', 'HOUR'];
            foreach ($filter_columns_accident AS $columns) {
                echo "<div>
    <select class=\"js-2select-dropbox\" id='filter_" . $columns . "' name=\"state\">";

                echo "<option value='ALL'>All</option>
  
</select>
<label for ='filter_" . $columns . "'>" . $columns . "<label>
</div>
    <script>
    $.ajax({
        type: 'POST',
        url: 'main.php',
        datatype: 'json',
        data: {        
            column_name: '" . $columns . "'
        },
        success: createDropdownAddFunction('" . $columns . "')
// add to the
    });
</script>";
            }
            foreach ($numerical_columns AS $columns) {
                echo "<div>
    <select class=\"js-2select-dropbox\" id='filter_" . $columns . "' name=\"state\">";

                echo "<option value='>='>>=</option>
    <option value='<='><=</option>
    <option value='='>=</option>
  
</select>
<input id='filter_input_" . $columns . "' value=0 type=\"number\">
<label for ='filter_" . $columns . "'>" . $columns . "<label>
</div>
 <script>
    $('#filter_" . $columns . "').select2({
                width: 50
                //dropdownAutoWidth: true
            });
</script>";
            }


            ?>
            <script>$(".header").click(function () {

                    $header = $(this);
                    //getting the next element
                    $content = $header.next();
                    //open up the content needed - toggle the slide- if visible, slide up, if not slidedown.
                    $content.slideToggle(500, function () {
                        //execute this after slideToggle is done
                        //change text of header based on visibility of content div
                        $header.children("button").text(function () {
                            //change text based on condition
                            return $content.is(":visible") ? "Collapse Filters" : "Expand Filters";
                        });
                    });

                });</script>
        </div>
    </div>
</div>
<div class="chart-container">
    <canvas id="myChart" width="400" height="400"></canvas>
</div>

<script>
    function onlyUnique(value, index, self) {
        return self.indexOf(value) === index;
    }

    function countTuples(){
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                countTuples: true,
            },
            success: console.log
// add to the
        });
    }

    function accidentsByHourDay(){
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                accidentsByHourDay: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsAccidentsByHourDay
// add to the
        });
    }

    function createChartsAccidentsByHourDay(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');

        data2 = JSON.parse(data2);
        chart_data = {};
        chart_labels_x = [];
        for (row of data2['rows']) {
            if (!chart_data.hasOwnProperty(row['DAY_OF_WEEK'])) {
                chart_data[row['DAY_OF_WEEK']] = [];
            }

            chart_data[row['DAY_OF_WEEK']].push({
                x: row['ACCIDENT_HOUR'],
                y: row['HOURLY_NUMBER_OF_ACCIDENTS']
            });
            chart_labels_x.push(row['ACCIDENT_HOUR']);
        }
        let datasets = []
        for (let condition in chart_data) {
            datasets.push({label: condition, data: chart_data[condition], pointRadius: 10,})
        }

        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'line',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                //labels: chart_labels_x,
                datasets
            },
            options: {
                scales: {
                    xAxes: [{
                        type: 'category',
                        labels: chart_labels_x.filter(onlyUnique)
                    }]
                }
            }
        });
    }

    function overallConditionsCausingHighestCasualtiesMonthYear() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                conditionsCausingHighestCasualtiesMonthYearOverall: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsconditionsCausingHighestCasualtiesMonthYear
// add to the
        });
    }

    function conditionsCausingHighestCasualtiesMonthYear() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                conditionsCausingHighestCasualtiesMonthYear: true,
                condition: document.getElementById('conditionCheck').value,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsconditionsCausingHighestCasualtiesMonthYear
// add to the
        });
    }

    function createChartsconditionsCausingHighestCasualtiesMonthYear(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');

        data2 = JSON.parse(data2);
        chart_data = {};
        chart_labels_x = [];
        for (row of data2['rows']) {
            if (!chart_data.hasOwnProperty(row['CONDITION'])) {
                chart_data[row['CONDITION']] = [];
            }

            chart_data[row['CONDITION']].push({
                x: row['ACCIDENT_YEAR'] + '-' + row['MONTH'],
                y: row['TOTAL_CASUALITIES']
            });
            chart_labels_x.push(row['ACCIDENT_YEAR'] + '-' + row['MONTH']);
        }
        let datasets = []
        for (let condition in chart_data) {
            datasets.push({label: condition, data: chart_data[condition], pointRadius: 10,})
        }

        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'scatter',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                //labels: chart_labels_x,
                datasets
            },
            options: {
                scales: {
                    xAxes: [{
                        type: 'category',
                        labels: chart_labels_x.filter(onlyUnique)
                    }]
                }
            }
        });
    }

    function yearDayTotalCasualties() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                yearDayTotalCasualties: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsYearDayTotalCasualties
// add to the
        });

    }

    function createChartsYearDayTotalCasualties(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');
        data2 = JSON.parse(data2);
        chart_data = {};
        chart_labels = [];
        for (row of data2['rows']) {
            if (!chart_data.hasOwnProperty(row['ACCIDENT_YEAR'])) {
                chart_data[row['ACCIDENT_YEAR']] = [];
            }

            chart_data[row['ACCIDENT_YEAR']].push({x: parseInt(row['DAY_OF_THE_WEEK']), y: row['TOTAL_CASUALITIES']});
            chart_labels.push(row['DAY_OF_THE_WEEK']);
        }
        let datasets = []
        for (let year in chart_data) {
            datasets.push({label: 'Total Casualties by Day for Year: ' + year, data: chart_data[year]})
        }

        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'line',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: chart_labels.filter(onlyUnique),
                datasets
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    }

    function accidentHotspotsByTime() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                accidentHotSpotsByTime: true,
                area_size: document.getElementById('inputAreaSize').value,
                min_num_accidents: document.getElementById('inputNumOfAccidentsInArea').value,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsAccidentHotspotsByTime
// add to the
        });

    }

    function createChartsAccidentHotspotsByTime(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');
        data2 = JSON.parse(data2);
        chart_data = [];
        chart_labels = [];
        for (row of data2['rows']) {
            chart_data.push(parseInt(row['NUM_OF_HOTSPOTS']));
            chart_labels.push(row['YEAR']);
        }
        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'bar',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: chart_labels,
                datasets: [{
                    label: 'Number of hotspots with given parameters',
                    data: chart_data,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    }

    function accidentBySpeedLimit() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                accidentBySpeedLimit: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsAccidentBySpeedLimit
// add to the
        });

    }

    function createChartsAccidentBySpeedLimit(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');
        data2 = JSON.parse(data2);
        chart_data = [];
        chart_labels = [];
        for (row of data2['rows']) {
            chart_data.push(row['NO_ACCIDENTS']);
            chart_labels.push(row['SPEED_LIMIT']);
        }
        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'bar',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: chart_labels,
                datasets: [{
                    label: 'Number of highest Casualities by Speed Limit',
                    data: chart_data,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });

    }

    function monthWithHighestAccident() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                monthWithHighestAccident: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createChartsMonthWithHighestAccident
// add to the
        });

    }

    function createChartsMonthWithHighestAccident(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');
        data2 = JSON.parse(data2);
        chart_data = [];
        chart_labels = [];
        for (row of data2['rows']) {
            chart_data.push(row['NO_OF_CASUALITIES']);
            chart_labels.push(row['YEAR'] + "-" + row['MONTH']);
        }
        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'bar',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: chart_labels,
                datasets: [{
                    label: 'Number of highest Casualities of Month by Year',
                    data: chart_data,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });

    }

    function chartTrendMonth() {
        var boundsInLonLat = getCurrentMapBounds();
        $.ajax({
            type: "POST",
            url: "./main.php",
            datatype: "json",
            data: {
                trendMonth: true,
                map_bounds: boundsInLonLat,
                filters: getFilterColumns()
            },
            success: createCharts

// add to the
        });

    }

    function createCharts(data2) {
        console.log(data2)
        var ctx = document.getElementById('myChart').getContext('2d');
        data2 = JSON.parse(data2);
        chart_data = {};
        chart_labels_x = [];
        for (row of data2['rows']) {
            if (!chart_data.hasOwnProperty(row['YEAR'])) {
                chart_data[row['YEAR']] = [];
            }

            chart_data[row['YEAR']].push({x: row['MONTH'], y: row['CHANGE']});
            chart_labels_x.push(row['MONTH']);
        }
        let datasets = []
        for (let year in chart_data) {
            datasets.push({label: year, data: chart_data[year]})
        }

        if (my_Chart) my_Chart.destroy();
        my_Chart = new Chart(ctx, {
            type: 'line',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: chart_labels_x.filter(onlyUnique),
                datasets
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });

    }
</script>
</body>



