<?php

/*
 * Zimbabwe location catalogue used by LocationSeeder.
 *
 * - cities:    [name, province, latitude, longitude] — approximate town-centre coordinates.
 * - suburbs:   city => zone => density => [suburb names]. Suburb coordinates are left
 *              empty on purpose; the owner's pin (or a later geocode) is authoritative.
 * - landmarks: [name, city, suburb|null, category] — places tenants search around.
 * - popular:   city => [suburb names] marked as rental hotspots.
 */

return [
    'cities' => [
        // Harare Metropolitan
        ['Harare', 'Harare', -17.8292, 31.0522],
        ['Chitungwiza', 'Harare', -18.0127, 31.0756],
        ['Epworth', 'Harare', -17.8900, 31.1475],

        // Bulawayo Metropolitan
        ['Bulawayo', 'Bulawayo', -20.1500, 28.5833],

        // Manicaland
        ['Mutare', 'Manicaland', -18.9707, 32.6709],
        ['Rusape', 'Manicaland', -18.5278, 32.1283],
        ['Chipinge', 'Manicaland', -20.1883, 32.6236],
        ['Nyanga', 'Manicaland', -18.2167, 32.7500],
        ['Chimanimani', 'Manicaland', -19.8000, 32.8667],
        ['Buhera', 'Manicaland', -19.3333, 31.4333],
        ['Headlands', 'Manicaland', -18.2833, 32.0500],
        ['Penhalonga', 'Manicaland', -18.8908, 32.6850],

        // Mashonaland Central
        ['Bindura', 'Mashonaland Central', -17.3019, 31.3306],
        ['Mount Darwin', 'Mashonaland Central', -16.7725, 31.5839],
        ['Shamva', 'Mashonaland Central', -17.3167, 31.5667],
        ['Glendale', 'Mashonaland Central', -17.3547, 31.0667],
        ['Mvurwi', 'Mashonaland Central', -17.0333, 30.8500],
        ['Guruve', 'Mashonaland Central', -16.6500, 30.7000],
        ['Concession', 'Mashonaland Central', -17.3833, 30.9500],

        // Mashonaland East
        ['Marondera', 'Mashonaland East', -18.1853, 31.5519],
        ['Ruwa', 'Mashonaland East', -17.8897, 31.2447],
        ['Chivhu', 'Mashonaland East', -19.0211, 30.8922],
        ['Goromonzi', 'Mashonaland East', -17.8667, 31.3667],
        ['Murehwa', 'Mashonaland East', -17.6500, 31.7833],
        ['Mutoko', 'Mashonaland East', -17.4000, 32.2167],
        ['Beatrice', 'Mashonaland East', -18.2500, 30.8500],
        ['Macheke', 'Mashonaland East', -18.1333, 31.8500],

        // Mashonaland West
        ['Chinhoyi', 'Mashonaland West', -17.3667, 30.2000],
        ['Kadoma', 'Mashonaland West', -18.3333, 29.9167],
        ['Chegutu', 'Mashonaland West', -18.1302, 30.1407],
        ['Norton', 'Mashonaland West', -17.8833, 30.7000],
        ['Kariba', 'Mashonaland West', -16.5167, 28.8000],
        ['Karoi', 'Mashonaland West', -16.8099, 29.6925],
        ['Banket', 'Mashonaland West', -17.3833, 30.4000],
        ['Chirundu', 'Mashonaland West', -16.0333, 28.8500],
        ['Mhangura', 'Mashonaland West', -16.9000, 30.1500],

        // Masvingo
        ['Masvingo', 'Masvingo', -20.0744, 30.8328],
        ['Chiredzi', 'Masvingo', -21.0500, 31.6667],
        ['Triangle', 'Masvingo', -21.0333, 31.4500],
        ['Gutu', 'Masvingo', -19.6500, 31.1667],
        ['Zaka', 'Masvingo', -20.3333, 31.4667],
        ['Mashava', 'Masvingo', -20.0333, 30.4833],
        ['Bikita', 'Masvingo', -20.0833, 31.6000],
        ['Mwenezi', 'Masvingo', -21.4167, 30.7333],

        // Matabeleland North
        ['Victoria Falls', 'Matabeleland North', -17.9243, 25.8572],
        ['Hwange', 'Matabeleland North', -18.3645, 26.4988],
        ['Lupane', 'Matabeleland North', -18.9315, 27.8070],
        ['Binga', 'Matabeleland North', -17.6203, 27.3414],
        ['Dete', 'Matabeleland North', -18.6167, 26.8667],
        ['Nkayi', 'Matabeleland North', -19.0000, 28.9000],

        // Matabeleland South
        ['Gwanda', 'Matabeleland South', -20.9333, 29.0000],
        ['Beitbridge', 'Matabeleland South', -22.2167, 30.0000],
        ['Plumtree', 'Matabeleland South', -20.4833, 27.8167],
        ['Esigodini', 'Matabeleland South', -20.2833, 28.9333],
        ['Filabusi', 'Matabeleland South', -20.5333, 29.2833],
        ['Kezi', 'Matabeleland South', -20.9167, 28.4667],
        ['West Nicholson', 'Matabeleland South', -21.0500, 29.3667],

        // Midlands
        ['Gweru', 'Midlands', -19.4500, 29.8167],
        ['Kwekwe', 'Midlands', -18.9281, 29.8149],
        ['Redcliff', 'Midlands', -19.0333, 29.7833],
        ['Zvishavane', 'Midlands', -20.3267, 30.0665],
        ['Shurugwi', 'Midlands', -19.6700, 30.0000],
        ['Gokwe', 'Midlands', -18.2167, 28.9333],
        ['Mvuma', 'Midlands', -19.2792, 30.5283],
    ],

    'suburbs' => [
        'Harare' => [
            'CBD' => [
                'commercial' => ['Harare CBD', 'Kopje'],
                'medium' => ['Avenues', 'Milton Park', 'Belvedere', 'Arcadia'],
            ],
            'Harare North' => [
                'low' => [
                    'Borrowdale', 'Borrowdale Brooke', 'Borrowdale West', 'Mount Pleasant', 'Mount Pleasant Heights',
                    'Avondale', 'Avondale West', 'Alexandra Park', 'Belgravia', 'Gunhill', 'Emerald Hill', 'Vainona',
                    'Pomona', 'Marlborough', 'Meyrick Park', 'Mabelreign', 'Strathaven', 'Groombridge',
                    'Ballantyne Park', 'Hogerty Hill', 'Philadelphia', 'Rolf Valley', 'Helensvale', 'Quinnington',
                    'Carrick Creagh', 'Colne Valley', 'Sentosa', 'Northwood', 'Avonlea', 'Kensington', 'Glen Forest',
                    'Greystone Park', 'Tynwald North',
                ],
                'high' => ['Hatcliffe'],
            ],
            'Harare East' => [
                'low' => [
                    'Highlands', 'Chisipite', 'Glen Lorne', 'Greendale', 'Mandara', 'Newlands', 'Rhodesville',
                    'Lewisam', 'Athlone', 'Ryelands', 'Eastlea', 'Coronation Park', 'Shawasha Hills',
                    'Grobbie Park', 'Hillside', 'Logan Park',
                ],
                'medium' => ['Msasa Park', 'Kambanji'],
                'high' => ['Mabvuku', 'Tafara', 'Caledonia'],
                'industrial' => ['Msasa'],
            ],
            'Harare West' => [
                'low' => ['Bluff Hill', 'Ashdown Park', 'Tynwald', 'Monavale', 'Marimba Park', 'Haydon Park'],
                'medium' => ['Westlea', 'Madokero'],
                'high' => [
                    'Warren Park', 'Kuwadzana', 'Kuwadzana Extension', 'Dzivarasekwa', 'Dzivarasekwa Extension',
                    'Mufakose', 'Budiriro', 'Glen View', 'Glen Norah', 'Highfield', 'Kambuzuma', 'Rugare',
                    'Lochinvar', 'Crowborough',
                ],
                'industrial' => ['Willowvale', 'Workington'],
            ],
            'Harare South' => [
                'low' => ['Hatfield', 'Prospect', 'Chadcombe', 'Cranborne', 'Queensdale', 'Houghton Park'],
                'medium' => [
                    'Waterfalls', 'Parktown', 'Mainway Meadows', 'Braeside', 'Sunningdale', 'Ardbennie',
                    'Park Meadowlands', 'Southlea Park', 'Stoneridge Park', 'St Martins',
                ],
                'high' => ['Mbare', 'Hopley', 'Ushewokunze', 'Southlea', 'Retreat'],
                'industrial' => ['Southerton', 'Graniteside'],
            ],
        ],

        'Chitungwiza' => [
            'Chitungwiza' => [
                'high' => [
                    "St Mary's", 'Zengeza 1', 'Zengeza 2', 'Zengeza 3', 'Zengeza 4', 'Zengeza 5',
                    'Seke Unit A', 'Seke Unit B', 'Seke Unit C', 'Seke Unit D', 'Seke Unit E', 'Seke Unit F',
                    'Seke Unit G', 'Seke Unit H', 'Seke Unit J', 'Seke Unit K', 'Seke Unit L', 'Seke Unit M',
                    'Seke Unit N', 'Seke Unit O', 'Makoni', 'Manyame Park',
                ],
            ],
        ],

        'Epworth' => [
            'Epworth' => [
                'high' => ['Domboramwari', 'Chinamano', 'Overspill', 'Makomo'],
            ],
        ],

        'Ruwa' => [
            'Ruwa' => [
                'medium' => ['Zimre Park', 'Damofalls', 'Windsor Park', 'Sunway City'],
                'high' => ['Chipukutu', 'Fairview'],
            ],
        ],

        'Norton' => [
            'Norton' => [
                'medium' => ['Galloway', 'Knowe'],
                'high' => ['Katanga', 'Ngoni', 'Nharira'],
            ],
        ],

        'Bulawayo' => [
            'Bulawayo Central' => [
                'commercial' => ['Bulawayo CBD'],
                'medium' => ['City Centre Flats', 'Suburbs', 'Kumalo', 'Parklands', 'Richmond'],
            ],
            'Eastern Suburbs' => [
                'low' => [
                    'Hillside', 'Burnside', 'Famona', 'Malindela', 'Morningside', 'Paddonhurst', 'Ilanda', 'Bradfield',
                    'Barham Green', 'Matsheumhlope', 'Southwold', 'Fortunes Gate', 'Douglasdale', 'Four Winds',
                    'Woodville', 'Kumalo East',
                ],
            ],
            'Northern Suburbs' => [
                'low' => [
                    'Killarney', 'Northend', 'Sauerstown', 'Riverside', 'Woodlands', 'Glencoe', 'Romney Park',
                    'Montrose', 'Waterford', 'Selbourne Park', 'Queens Park', 'Northlea', 'Newton West',
                    'Harrisvale', 'Upper Rangemore',
                ],
                'high' => ['Cowdray Park', 'Mahatshula'],
            ],
            'Western Suburbs' => [
                'high' => [
                    'Entumbane', 'Emakhandeni', 'Luveve', 'Lobengula', 'Magwegwe', 'Mpopoma', 'Mzilikazi', 'Makokoba',
                    'Nkulumane', 'Pumula', 'Tshabalala', 'Sizinda', 'Nketa', 'Njube', 'Nguboyenja', 'Emganwini',
                    'Mabutweni', 'Iminyela', 'Pelandaba', 'Gwabalanda', 'Barbourfields', 'Old Magwegwe',
                    'Lobengula West', 'Hyde Park',
                ],
            ],
            'Industrial' => [
                'industrial' => ['Belmont', 'Kelvin', 'Donnington', 'Steeldale', 'Thorngrove'],
            ],
        ],

        'Mutare' => [
            'Mutare' => [
                'commercial' => ['Mutare CBD'],
                'low' => [
                    'Murambi', "Tiger's Kloof", 'Greenside', 'Palmerston', 'Morningside', 'Fairbridge Park',
                    'Darlington', 'Bordervale', 'Fern Valley', 'Utopia',
                ],
                'medium' => ['Yeovil', 'Florida'],
                'high' => ['Chikanga', 'Dangamvura', 'Sakubva', 'Hobhouse'],
            ],
        ],

        'Gweru' => [
            'Gweru' => [
                'commercial' => ['Gweru CBD'],
                'low' => ['Ascot', 'Riverside', 'Southdowns', 'Daylesford', 'Lundi Park', 'Windsor Park', 'Northlea', 'Ridgemont', 'Nashville'],
                'medium' => ['Kopje', 'Mtapa', 'Ivene'],
                'high' => ['Mkoba', 'Senga', 'Nehosho', 'Mambo', 'Ascot Extension'],
            ],
        ],

        'Kwekwe' => [
            'Kwekwe' => [
                'commercial' => ['Kwekwe CBD'],
                'low' => ['Newtown', 'Fitchlea', 'Garden Park', 'Msasa Park'],
                'high' => ['Mbizo', 'Amaveni'],
            ],
        ],

        'Redcliff' => [
            'Redcliff' => [
                'medium' => ['Torwood'],
                'high' => ['Rutendo'],
            ],
        ],

        'Masvingo' => [
            'Masvingo' => [
                'commercial' => ['Masvingo CBD'],
                'low' => ['Rhodene', 'Eastvale', 'Morningside', 'Target Kopje', 'Clipsham Park'],
                'medium' => ['Victoria Ranch'],
                'high' => ['Mucheke', 'Rujeko', 'Runyararo'],
            ],
        ],

        'Kadoma' => [
            'Kadoma' => [
                'commercial' => ['Kadoma CBD'],
                'low' => ['Waverley', 'Eiffel Flats', 'Westview'],
                'high' => ['Rimuka', 'Ingezi'],
            ],
        ],

        'Chinhoyi' => [
            'Chinhoyi' => [
                'commercial' => ['Chinhoyi CBD'],
                'low' => ['Orange Grove', 'Cold Stream', 'Brundish'],
                'high' => ['Chikonohono', 'Hunyani', 'Gadzema', 'Mzari'],
            ],
        ],

        'Marondera' => [
            'Marondera' => [
                'commercial' => ['Marondera CBD'],
                'low' => ['Paradise Park', 'Ruzawi Park', 'Yellow City'],
                'high' => ['Dombotombo', 'Nyameni', 'Cherutombo', 'Rujeko'],
            ],
        ],

        'Bindura' => [
            'Bindura' => [
                'commercial' => ['Bindura CBD'],
                'medium' => ['Aerodrome'],
                'high' => ['Chipadze', 'Chiwaridzo'],
            ],
        ],

        'Chegutu' => [
            'Chegutu' => [
                'high' => ['Pfupajena', 'Kaguvi'],
            ],
        ],

        'Rusape' => [
            'Rusape' => [
                'high' => ['Vengere', 'Magamba'],
            ],
        ],

        'Kariba' => [
            'Kariba' => [
                'low' => ['Kariba Heights'],
                'high' => ['Nyamhunga', 'Mahombekombe'],
            ],
        ],

        'Victoria Falls' => [
            'Victoria Falls' => [
                'commercial' => ['Victoria Falls Town Centre'],
                'high' => ['Chinotimba', 'Mkhosana'],
            ],
        ],

        'Hwange' => [
            'Hwange' => [
                'high' => ['Lwendulu', 'Empumalanga'],
            ],
        ],

        'Beitbridge' => [
            'Beitbridge' => [
                'high' => ['Dulivhadzimu'],
            ],
        ],

        'Gwanda' => [
            'Gwanda' => [
                'medium' => ['Spitzkop'],
                'high' => ['Jahunda', 'Phakama'],
            ],
        ],

        'Zvishavane' => [
            'Zvishavane' => [
                'high' => ['Mandava', 'Maglas'],
            ],
        ],

        'Karoi' => [
            'Karoi' => [
                'high' => ['Chiedza'],
            ],
        ],
    ],

    'landmarks' => [
        // Harare
        ["Sam Levy's Village", 'Harare', 'Borrowdale', 'shopping'],
        ['Arundel Village', 'Harare', 'Mount Pleasant', 'shopping'],
        ['Avondale Shopping Centre', 'Harare', 'Avondale', 'shopping'],
        ['Westgate Shopping Centre', 'Harare', 'Mabelreign', 'shopping'],
        ['Eastgate Centre', 'Harare', 'Harare CBD', 'shopping'],
        ['Joina City', 'Harare', 'Harare CBD', 'shopping'],
        ['Highland Park Shopping Centre', 'Harare', 'Highlands', 'shopping'],
        ['Chisipite Shopping Centre', 'Harare', 'Chisipite', 'shopping'],
        ['Newlands Shopping Centre', 'Harare', 'Newlands', 'shopping'],
        ['Kamfinsa Shopping Centre', 'Harare', 'Greendale', 'shopping'],
        ['Belgravia Shopping Centre', 'Harare', 'Belgravia', 'shopping'],
        ['University of Zimbabwe', 'Harare', 'Mount Pleasant', 'education'],
        ['Harare Polytechnic', 'Harare', 'Harare CBD', 'education'],
        ['Parirenyatwa Group of Hospitals', 'Harare', 'Avenues', 'health'],
        ['Sally Mugabe Central Hospital', 'Harare', 'Southerton', 'health'],
        ['Robert Gabriel Mugabe International Airport', 'Harare', null, 'transport'],
        ['Mbare Musika', 'Harare', 'Mbare', 'market'],
        ['Road Port Terminal', 'Harare', 'Harare CBD', 'transport'],
        ['National Sports Stadium', 'Harare', 'Belvedere', 'recreation'],
        ['Harare Gardens', 'Harare', 'Harare CBD', 'recreation'],
        ['Borrowdale Racecourse', 'Harare', 'Borrowdale', 'recreation'],

        // Chitungwiza
        ['Chitungwiza Town Centre', 'Chitungwiza', 'Zengeza 2', 'shopping'],
        ['Chitungwiza Central Hospital', 'Chitungwiza', null, 'health'],
        ['Makoni Shopping Centre', 'Chitungwiza', 'Makoni', 'shopping'],

        // Bulawayo
        ['Bulawayo Centre', 'Bulawayo', 'Bulawayo CBD', 'shopping'],
        ['Ascot Shopping Centre', 'Bulawayo', null, 'shopping'],
        ['Hillside Shopping Centre', 'Bulawayo', 'Hillside', 'shopping'],
        ['National University of Science and Technology (NUST)', 'Bulawayo', null, 'education'],
        ['Bulawayo Polytechnic', 'Bulawayo', 'Bulawayo CBD', 'education'],
        ['Mpilo Central Hospital', 'Bulawayo', 'Mzilikazi', 'health'],
        ['United Bulawayo Hospitals', 'Bulawayo', null, 'health'],
        ['Joshua Mqabuko Nkomo International Airport', 'Bulawayo', null, 'transport'],
        ['Hillside Dams', 'Bulawayo', 'Hillside', 'recreation'],
        ['Centenary Park', 'Bulawayo', 'Suburbs', 'recreation'],

        // Other towns
        ['Mutare Provincial Hospital', 'Mutare', null, 'health'],
        ['Meikles Park', 'Mutare', 'Mutare CBD', 'recreation'],
        ['Midlands State University', 'Gweru', 'Senga', 'education'],
        ['Gweru Provincial Hospital', 'Gweru', null, 'health'],
        ['Great Zimbabwe University', 'Masvingo', null, 'education'],
        ['Chinhoyi University of Technology', 'Chinhoyi', null, 'education'],
        ['Bindura University of Science Education', 'Bindura', null, 'education'],
        ['Marondera University of Agricultural Sciences and Technology', 'Marondera', null, 'education'],
        ['Victoria Falls International Airport', 'Victoria Falls', null, 'transport'],
        ['Elephant Hills', 'Victoria Falls', null, 'recreation'],
    ],

    'popular' => [
        'Harare' => [
            'Borrowdale', 'Avondale', 'Mount Pleasant', 'Belgravia', 'Greendale', 'Highlands', 'Mabelreign',
            'Marlborough', 'Waterfalls', 'Hatfield', 'Eastlea', 'Milton Park', 'Avenues', 'Budiriro', 'Glen View',
            'Kuwadzana', 'Warren Park', 'Westlea', 'Madokero',
        ],
        'Chitungwiza' => ["St Mary's", 'Zengeza 3'],
        'Ruwa' => ['Zimre Park'],
        'Bulawayo' => ['Hillside', 'Suburbs', 'Famona', 'Kumalo', 'Nkulumane', 'Pumula', 'Cowdray Park'],
        'Mutare' => ['Murambi', 'Dangamvura', 'Chikanga'],
        'Gweru' => ['Mkoba', 'Senga'],
        'Masvingo' => ['Rhodene', 'Mucheke'],
    ],
];
