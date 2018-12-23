<?php

/**
 * Fais un appel GET à $url et retourne un tableau avec le contenu JSON en cas de succès
 * Sinon le message d'erreur en contenu
 * @param url    Url de l'API
 * @param name   Type de données retourné (utilisé pour les messages d'erreurs)
 * @return Array Resultat de l'API
 */
function getJSON($url, $name = "service"){
    $dataString = file_get_contents($url);
    $codeHTTP   = intval(explode(' ', $http_response_header[0])[1]);
   
    switch($codeHTTP){
        case 200 :
            $content = json_decode($dataString, true);
            break;
        case 404 :
            $content = "<div>Une erreur est survenue. Impossibler d'acceder aux $name.</div>";
            break;
        case 500 :
            $content = '<div>Une erreur interne est survenue. Veuillez réésayer plus tard</div>';
            break;
        default :
            $content = "<div>Une erreur liée à l'API est survenue. Impossible d'accéder au $name.</div>";
            break;
    }
    
    return [
        'code'    => $codeHTTP,
        'content' => $content
    ];
}

/* Proxy IUT */
if($_SERVER['HTTP_HOST'] == "webetu.iutnc.univ-lorraine.fr"){
    error_reporting(0);
    stream_context_set_default([
        'http' => [
            'proxy'           => 'tcp://www-cache.iutnc.univ-lorraine.fr:3128/', 
            'request_fulluri' => true
        ]
    ]);
}

$errorsHTML   = [];
$localisation = "[47.235,-1.5494]"; //défaut (~ Nantes)
$nantesCode   = 44109;
$data         = getJSON("https://geo.api.gouv.fr/commudnes/$nantesCode/?fields=centre", "centre de Nantes");

if($data['code'] != 200){
    $errorsHTML[] = $data['content'];
}else{
    $infos        = $data['content'];
    $localisation = "[" . $infos['centre']['coordinates'][1] . "," . $infos['centre']['coordinates'][0] . "]";
}



$scriptMap = <<<EOT
<script>
    $(function(){
        $('.errors div').on('click', function(e){
            $(e.currentTarget).fadeOut(200);
        });

        function generateMap(options){
            let optionsMap = Object.assign({
                token  : null,
                id     : "mapElement",
                center : [48.692054, 6.184416999999939], //Nancy,
                zoom   : 20
            }, options);
        
            let map = L.map(optionsMap.id, {
                center : optionsMap.center,
                zoom   : optionsMap.zoom
            });
           
            L.tileLayer("https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=" + optionsMap.token, {
                attribution : 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
                maxZoom     : 18,
                id          : 'mapbox.streets',
                accessToken : optionsMap.token
            }).addTo(map);
        
            return map;
        }

        let map = generateMap({
            token  : "pk.eyJ1IjoiY2x1bTU0NTQiLCJhIjoiY2pwdGltOGpqMDV4ejN4bzlkNmNhZTY2ZyJ9.vH6wlQl-AQuE0CG7ePKcOA",
            id     : "map",
            center : $localisation,
            zoom   : 12
        });
EOT;

$scriptMap .= "});</script>";

$errorRenderHTML  = "<div class='errors'>" . implode('', $errorsHTML) . '</div>';

$html = <<<EOT
    <!DOCTYPE html>
    <html>
        <head>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.css"/>
            <link rel="stylesheet" href="public/css/style.css"/>
        </head>

    <body>
        $errorRenderHTML
        <div id="map"></div>
        <!-- Script -->
        <script
            src="https://code.jquery.com/jquery-3.3.1.js"
            integrity="sha256-2Kok7MbOyxpgUVvAk/HJ2jigOSYS2auK4Pfzbm7uH60="
            crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.js"></script>
        $scriptMap
    </body>
    </html>
EOT;

echo $html;