<?php

/* URL : https://webetu.iutnc.univ-lorraine.fr/www/thouven33u/interoperabilite/trafficapp/ */

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
        case 404 :#
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
$data         = getJSON("https://geo.api.gouv.fr/communes/$nantesCode/?fields=centre", "centre de Nantes");

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

        let icon = null;
        let popup_content = null;
EOT;

$url_traffic = "https://data.nantesmetropole.fr/api/records/1.0/search/?dataset=244400404_fluidite-axes-routiers-nantes-metropole&facet=couleur_tp";
$data_traffic = getJSON($url_traffic,"infos traffic de la ville de Nantes");
if($data_traffic['code'] != 200){
    $errorsHTML[] = $data_traffic['content'];
} else {
    $records = $data_traffic['content']['records'];
    foreach($records as $record){
        $info = $record['fields'];
        $url_tronçon = "https://data.nantesmetropole.fr/api/records/1.0/search/?dataset=244400404_troncons-routiers-nantes-metropole&facet=id&refine.id={$info['id']}";
        $tronçon = getJSON($url_tronçon,'tronçons');
        if($tronçon['code'] == 200){
            $tronçon_info = $tronçon['content']['records'][0]['fields'];
            $tronçon_location = $tronçon['content']['records'][0]['fields']['geo_point_2d'];
            $localisation  = "["   . $tronçon_location[0]  . "," . $tronçon_location[1] . "]";
            $popup_content = "<b>".$tronçon_info['nom']."</b>    ".$tronçon_info['sens1']."<br>";
            $couleur = $info['couleur_tp'];
            $scriptMap .= <<<EOT
            popup_content = "$popup_content"
            switch ($couleur){
                case 0 :
            
                    break;
                case 1 :
                    icon = L.icon({
                        iconUrl: 'img/car.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "pas d'informations sur la circulation"
                    break;
                case 2 :
                    icon = L.icon({
                        iconUrl: 'img/car.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "informations manquantes pour  ce tronçon"
                    break;
                case 3 :
                    icon = L.icon({
                        iconUrl: 'img/sports-car.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "circulation fluide"
                    break;
                case 4 :
                    icon = L.icon({
                        iconUrl: 'img/sports-car-4.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "circulation dense"
                    break;
                case 5 :
                    icon = L.icon({
                        iconUrl: 'img/sports-car-3.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "circulation saturée"
                    break;
                case 6 :
                    icon = L.icon({
                        iconUrl: 'img/multiple-fender-bender.png',
                        iconSize: [30, 40],
                    })
                    popup_content += "circulation arrétée"
                    break;
                default :
                    break;
            }

            marker = L.marker($localisation, {icon : icon});
            marker.bindPopup(popup_content);
            marker.addTo(map);
EOT;
        }        
    }
}

$scriptMap .= "});</script>";


$url_nasa = "https://api.nasa.gov/mars-photos/api/v1/rovers/curiosity/photos?sol=1000&api_key=kdlIh4yvdWnDv8ag5AYZpCrlYWU8dfU4V1fACMc0";
$data_photos = getJSON($url_nasa, "photos de curiosity");
if($data_photos['code'] != 200){
    $errorsHTML[] = $data_photos['content'];
} else {
    $photos = $data_photos['content']['photos'];
    foreach($photos as $photo){
        if($photo['camera']['name'] == "RHAZ"){
            $src = $photo['img_src'];
            $back_photos = "<img src='$src'>";
        }
    }
}


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
        <div id="curiosity">$back_photos</div>
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