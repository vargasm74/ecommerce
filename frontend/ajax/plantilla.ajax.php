<?php

require_once "../modelos/seguridad.php";
Seguridad::iniciarSesion();
Seguridad::exigirCsrf();

require_once "../controladores/plantilla.controlador.php";
require_once "../modelos/plantilla.modelo.php";

class AjaxPlantilla{

	public function ajaxEstiloPlantilla(){

		$respuesta = ControladorPlantilla::ctrEstiloPlantilla();

		echo json_encode($respuesta);

	}


}

$objeto = new AjaxPlantilla();
$objeto -> ajaxEstiloPlantilla();
