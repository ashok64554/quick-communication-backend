<?php

return [
	//'orientation'			=> 'L',
	'mode'                  => 'utf-8',
	'format'                => 'A4',
	'author'                => 'NewRise Technosys Pvt. Ltd.',
	'subject'               => '',
	'keywords'              => '',
	'creator'               => 'NewRise Technosys Pvt. Ltd.',
	'display_mode'          => 'fullpage',
	'tempDir'               => base_path('public/temp/'),
	'pdf_a'                 => false,
	'pdf_a_auto'            => false,
	'icc_profile_path'      => '',
	'font_path' => base_path('public/fonts/'),
	'font_data' => [
	  	'opensanscondensed' => [
	    	'B'  => 'OpenSans-CondBold.ttf',
	    	'R'  => 'OpenSans-CondLight.ttf',
	    	'I'  => 'OpenSans-CondLightItalic.ttf'
	  	], 
	]
];
