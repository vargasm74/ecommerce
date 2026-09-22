<?php

class ControladorCarrito{

	/*=============================================
	MOSTRAR TARIFAS
	=============================================*/

	public static function ctrMostrarTarifas(){

		$tabla = "comercio";

		$respuesta = ModeloCarrito::mdlMostrarTarifas($tabla);

		return $respuesta;

	}	

	/*=============================================
	CALCULAR RESUMEN SEGURO DE CHECKOUT
	=============================================*/

	private static function resolverReglaFiscal($reglas, $region, $tipoProducto){

		$mejorRegla = null;
		$mejorPuntaje = -1;

		foreach($reglas as $regla){

			$regionRegla = strtoupper(trim((string) $regla["region"]));
			$tipoRegla = strtolower(trim((string) $regla["tipo_producto"]));

			$coincideRegion = $regionRegla === "*" || $regionRegla === $region;
			$coincideTipo = $tipoRegla === "*" || $tipoRegla === $tipoProducto;

			if(!$coincideRegion || !$coincideTipo){
				continue;
			}

			$puntaje = 0;

			if($regionRegla !== "*"){
				$puntaje += 2;
			}

			if($tipoRegla !== "*"){
				$puntaje += 1;
			}

			if($puntaje > $mejorPuntaje){
				$mejorPuntaje = $puntaje;
				$mejorRegla = $regla;
			}
		}

		return $mejorRegla;
	}

	/*=============================================
	CALCULAR RESUMEN SEGURO DE CHECKOUT
	=============================================*/

	static public function ctrCalcularResumenCheckout($ids, $cantidades, $pais = "", $region = ""){

		if(!is_array($ids) || !is_array($cantidades) || count($ids) === 0 || count($ids) !== count($cantidades)){
			throw new InvalidArgumentException("Carrito invalido");
		}

		if(count($ids) > 50){
			throw new InvalidArgumentException("Demasiados productos en el carrito");
		}

		$pais = strtoupper(trim((string) $pais));
		$region = strtoupper(trim((string) $region));

		if($pais !== "" && !preg_match('/^[A-Z]{2}$/', $pais)){
			throw new InvalidArgumentException("Pais invalido");
		}

		if(strlen($region) > 100){
			throw new InvalidArgumentException("Region invalida");
		}

		$tarifas = ModeloCarrito::mdlMostrarTarifas("comercio");

		if(!$tarifas){
			throw new RuntimeException("No hay configuracion de comercio");
		}

		$paisSeleccionado = $pais !== "";
		$paisFiscal = $paisSeleccionado
			? $pais
			: strtoupper(trim((string) $tarifas["pais"]));

		$reglasFiscales = ModeloCarrito::mdlBuscarReglasFiscales($paisFiscal);

		if(count($reglasFiscales) === 0){

			if($paisSeleccionado){
				throw new RuntimeException("No hay una regla fiscal configurada para el pais seleccionado");
			}

			$reglasFiscales[] = array(
				"id" => null,
				"pais" => $paisFiscal,
				"region" => "*",
				"tipo_producto" => "*",
				"porcentaje" => max(0, (float) $tarifas["impuesto"]),
				"descripcion" => "Configuracion heredada de comercio.impuesto"
			);
		}

		$subtotalCentavos = 0;
		$impuestoCentavos = 0;
		$pesoTotal = 0.0;
		$requiereEnvio = false;
		$productos = array();
		$porcentajesAplicados = array();

		foreach($ids as $indice => $idProducto){

			$idProducto = filter_var($idProducto, FILTER_VALIDATE_INT, array(
				"options" => array("min_range" => 1)
			));

			$cantidad = filter_var($cantidades[$indice], FILTER_VALIDATE_INT, array(
				"options" => array("min_range" => 1, "max_range" => 100)
			));

			if($idProducto === false || $cantidad === false){
				throw new InvalidArgumentException("Producto o cantidad invalida");
			}

			$producto = ModeloCarrito::mdlObtenerProductoCheckout($idProducto);

			if(!$producto){
				throw new InvalidArgumentException("Producto inexistente");
			}

			$tipoProducto = strtolower(trim((string) $producto["tipo"]));
			$reglaFiscal = self::resolverReglaFiscal($reglasFiscales, $region, $tipoProducto);

			if(!$reglaFiscal){
				throw new RuntimeException("No hay una regla fiscal aplicable al producto ".$producto["id"]);
			}

			$precioBase = (float) $producto["precio"];
			$precioOferta = (float) $producto["precioOferta"];
			$precioReal = $precioOferta > 0 ? $precioOferta : $precioBase;
			$precioCentavos = (int) round($precioReal * 100);
			$subtotalItemCentavos = $precioCentavos * $cantidad;

			$porcentajeImpuesto = max(0, (float) $reglaFiscal["porcentaje"]);
			$impuestoItemCentavos = (int) round(
				$subtotalItemCentavos * ($porcentajeImpuesto / 100)
			);

			$subtotalCentavos += $subtotalItemCentavos;
			$impuestoCentavos += $impuestoItemCentavos;
			$porcentajesAplicados[number_format($porcentajeImpuesto, 2, ".", "")] = true;

			$pesoItem = 0.0;

			if($tipoProducto === "fisico"){
				$requiereEnvio = true;
				$pesoItem = max(0, (float) $producto["peso"]) * $cantidad;
				$pesoTotal += $pesoItem;
			}

			$productos[] = array(
				"id" => (int) $producto["id"],
				"titulo" => (string) $producto["titulo"],
				"tipo" => $tipoProducto,
				"cantidad" => $cantidad,
				"precio_unitario" => number_format($precioCentavos / 100, 2, ".", ""),
				"subtotal" => number_format($subtotalItemCentavos / 100, 2, ".", ""),
				"impuesto_porcentaje" => number_format($porcentajeImpuesto, 2, ".", ""),
				"impuesto" => number_format($impuestoItemCentavos / 100, 2, ".", ""),
				"regla_fiscal_id" => $reglaFiscal["id"] === null ? null : (int) $reglaFiscal["id"],
				"regla_fiscal" => (string) ($reglaFiscal["descripcion"] ?? ""),
				"peso_total" => round($pesoItem, 3)
			);
		}

		$envioCentavos = 0;

		if($requiereEnvio && $pais !== ""){

			$esNacional = strtoupper((string) $tarifas["pais"]) === $pais;
			$tarifaEnvio = (float) ($esNacional ? $tarifas["envioNacional"] : $tarifas["envioInternacional"]);
			$minimoEnvio = (float) ($esNacional ? $tarifas["tasaMinimaNal"] : $tarifas["tasaMinimaInt"]);
			$envioCalculado = $pesoTotal * max(0, $tarifaEnvio);
			$envioReal = max($envioCalculado, max(0, $minimoEnvio));
			$envioCentavos = (int) round($envioReal * 100);
		}

		$totalCentavos = $subtotalCentavos + $impuestoCentavos + $envioCentavos;
		$listaPorcentajes = array_keys($porcentajesAplicados);
		$impuestoDescripcion = count($listaPorcentajes) === 1
			? $listaPorcentajes[0]."%"
			: "Variable por producto";

		return array(
			"moneda" => "USD",
			"productos" => $productos,
			"subtotal" => number_format($subtotalCentavos / 100, 2, ".", ""),
			"impuesto_descripcion" => $impuestoDescripcion,
			"impuesto" => number_format($impuestoCentavos / 100, 2, ".", ""),
			"envio" => number_format($envioCentavos / 100, 2, ".", ""),
			"total" => number_format($totalCentavos / 100, 2, ".", ""),
			"peso_total" => round($pesoTotal, 3),
			"requiere_envio" => $requiereEnvio,
			"pais_envio" => $pais,
			"pais_fiscal" => $paisFiscal,
			"region_fiscal" => $region
		);
	}

	/*=============================================
	VERIFICAR COMPRA DE USUARIO
	=============================================*/

	static public function ctrUsuarioTieneCompra($idUsuario, $idProducto){

		$idUsuario = filter_var($idUsuario, FILTER_VALIDATE_INT, array(
			"options" => array("min_range" => 1)
		));
		$idProducto = filter_var($idProducto, FILTER_VALIDATE_INT, array(
			"options" => array("min_range" => 1)
		));

		if($idUsuario === false || $idProducto === false){
			return false;
		}

		return ModeloCarrito::mdlUsuarioTieneCompra($idUsuario, $idProducto);
	}

	/*=============================================
	ADQUIRIR PRODUCTO GRATIS
	=============================================*/

	static public function ctrAdquirirProductoGratis($idUsuario, $idProducto, $email, $detalle){

		$idUsuario = filter_var($idUsuario, FILTER_VALIDATE_INT, array(
			"options" => array("min_range" => 1)
		));
		$idProducto = filter_var($idProducto, FILTER_VALIDATE_INT, array(
			"options" => array("min_range" => 1)
		));

		if($idUsuario === false || $idProducto === false){
			return "error";
		}

		$email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : "";
		$detalle = trim(strip_tags((string) $detalle));

		if(strlen($detalle) > 500){
			$detalle = substr($detalle, 0, 500);
		}

		return ModeloCarrito::mdlAdquirirProductoGratis(
			$idUsuario,
			$idProducto,
			$email,
			$detalle
		);
	}

	/*=============================================
	NUEVAS COMPRAS
	=============================================*/

	static public function ctrNuevasCompras($datos){

		$tabla = "compras";

		$respuesta = ModeloCarrito::mdlNuevasCompras($tabla, $datos);

		if($respuesta == "ok"){

			$tabla = "comentarios";
			ModeloUsuarios::mdlIngresoComentarios($tabla, $datos);

		}

		return $respuesta;

	}
}