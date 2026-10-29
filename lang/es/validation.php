<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'Ingresa un correo electrónico válido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'mimetypes' => 'El archivo :attribute debe ser de tipo: :values.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'digits' => 'El campo :attribute debe contener exactamente :digits dígitos.',    
    'same' => 'El campo :attribute debe coincidir con :other.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'url' => 'Ingresa una dirección URL válida.',
    'exists' => 'El valor seleccionado para :attribute no es válido.',

    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe tener al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe contener al menos :min caracteres.',
    ],

    'max' => [
        'array' => 'El campo :attribute no debe superar :max elementos.',
        'file' => 'El archivo :attribute no debe superar :max kilobytes.',
        'numeric' => 'El campo :attribute no debe superar :max.',
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],

    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El archivo :attribute debe tener :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe contener exactamente :size caracteres.',
    ],

    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El archivo :attribute debe tener entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe contener entre :min y :max caracteres.',
    ],

    'attributes' => [
        'name' => 'nombre',
        'nombre' => 'nombre',
        'email' => 'correo electrónico',
        'correo' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'curp' => 'CURP',
        'telefono' => 'teléfono',
        'link' => 'enlace',
        'constancia_antiguedad_archivo' => 'constancia de antigüedad',
        'comprobante_pago_archivo' => 'comprobante de pago',
	'rfc' => 'RFC',
	'codigo_postal' => 'código postal',
	'correo_electronico' => 'correo electrónico',
	'nombres' => 'nombres',
	'apellido_paterno' => 'apellido paterno',
	'apellido_materno' => 'apellido materno',
	'certificacion' => 'certificación',
	'tipo_personal' => 'tipo de personal',
	'area_certificacion' => 'área de certificación',
	'nivel_origen' => 'nivel académico de origen',
	'titulo_origen' => 'título académico de origen',
	'nivel_ultimo_grado' => 'nivel del último grado académico',
	'titulo_ultimo_grado' => 'título del último grado académico',
	'institucion_afiliada_id' => 'institución afiliada',

	'titulo_origen_archivo' => 'archivo del título de origen',
	'cedula_origen_archivo' => 'archivo de la cédula de origen',
	'titulo_ultimo_grado_archivo' => 'archivo del título del último grado',
	'cedula_ultimo_grado_archivo' => 'archivo de la cédula del último grado',
    ],
		'custom' => [
	    'titulo_origen_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
	    'cedula_origen_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
	    'titulo_ultimo_grado_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
	    'cedula_ultimo_grado_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
	    'constancia_antiguedad_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
	    'comprobante_pago_archivo' => [
	        'max' => 'El archivo :attribute no debe superar los 5 MB.',
	    ],
		'institucion_afiliada_id' => [
		    'exists' => 'La institución afiliada seleccionada no es válida.',
		],
	],

];
