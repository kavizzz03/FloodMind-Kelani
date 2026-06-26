<?php
// get_locations.php
header('Content-Type: application/json');

$locations = [
    "Colombo" => [
        "Seethawaka Pradeshiya Sabha" => [
            "Avissawella Town Center", 
            "Puwakpitiya South", 
            "Puwakpitiya North",
            "Egodagama", 
            "Bollathawa", 
            "Kanampella East", 
            "Kanampella West", 
            "Manakada",
            "Eswatta North", 
            "Eswatta South", 
            "Kiriwandala North", 
            "Kiriwandala South",
            "Hanwella Patuwata", 
            "Ihala Hanwella South", 
            "Kosgama North", 
            "Kosgama South",
            "Pahala Hanwella"
        ],
        "Colombo Municipal Council" => [
            "Modara", 
            "Mattakkuliya", 
            "Kotahena", 
            "Bloemendhal",
            "Grandpass",
            "Nagalagam Street Area",
            "Dematagoda (Low-lying zones)",
            "Madampitiya"
        ],
        "Kolonnawa Pradeshiya Sabha" => [
            "Gotatuwa", 
            "Sedawatta", 
            "Ambatalenpahala", 
            "Kittampahuwa",
            "Wellampitiya",
            "Kelanimulla",
            "Udumulla North",
            "Meethotamulla"
        ],
        "Kaduwela Municipal Council" => [
            "Kaduwela Center", 
            "Ranala", 
            "Nawagamuwa", 
            "Welivita",
            "Ihala Bomiriya",
            "Pahala Bomiriya",
            "Malabe West",
            "Walpola",
            "Mahadeniya"
        ]
    ],
    "Gampaha" => [
        "Biyagama Pradeshiya Sabha" => [
            "Biyagama Center", 
            "Mawgama", 
            "Malwana", 
            "Yabaraluwa",
            "Pattiwila",
            "Walgedara"
        ],
        "Dompe Pradeshiya Sabha" => [
            "Dompe", 
            "Malwana Junction", 
            "Pugoda",
            "Weke"
        ],
        "Kelaniya Pradeshiya Sabha" => [
            "Kelaniya Center", 
            "Peliyagoda", 
            "Wanawasala", 
            "Sinharamulla",
            "Pethiyagoda",
            "Kohilawatta",
            "Thorana Junction Area"
        ],
        "Wattala Pradeshiya Sabha" => [
            "Hekitta",
            "Hendala (Lower Basin)",
            "Telangapatha",
            "Mabola"
        ]
    ],
    "Kegalle" => [
        "Ruwanwella Pradeshiya Sabha" => [
            "Ruwanwella", 
            "Karawanella", 
            "Kannattota",
            "Waharaka"
        ],
        "Dehiowita Pradeshiya Sabha" => [
            "Dehiowita", 
            "Algoda", 
            "Magammana",
            "Algama"
        ],
        "Deraniyagala Pradeshiya Sabha" => [
            "Deraniyagala Center", 
            "Maliboda", 
            "Anhettigama"
        ]
    ],
    "Nuwaraliya" => [
        "Ambagamuwa Pradeshiya Sabha" => [
            "Norwood", 
            "Kithulgala", 
            "Ginigathena", 
            "Maskeliya"
        ]
    ]
];

$type = $_GET['type'] ?? '';

if ($type === 'districts') {
    echo json_encode(array_keys($locations));
} elseif ($type === 'sabha') {
    $district = $_GET['district'] ?? '';
    echo json_encode(isset($locations[$district]) ? array_keys($locations[$district]) : []);
} elseif ($type === 'wasam') {
    $district = $_GET['district'] ?? '';
    $sabha = $_GET['sabha'] ?? '';
    echo json_encode($locations[$district][$sabha] ?? []);
} else {
    echo json_encode(["error" => "Invalid request type"]);
}