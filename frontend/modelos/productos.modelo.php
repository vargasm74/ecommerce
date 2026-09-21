<?php

require_once "conexion.php";

class ModeloProductos{

	private static function normalizarOrden($ordenar){
		$permitidos = array("id", "ventas", "vistas", "vistasGratis", "precio", "precioOferta", "titulo");

		return in_array($ordenar, $permitidos, true) ? $ordenar : "id";
	}

	private static function normalizarModo($modo){
		$modo = strtoupper((string) $modo);

		return in_array($modo, array("ASC", "DESC"), true) ? $modo : "DESC";
	}

	private static function normalizarLimite($valor, $porDefecto){
		$valor = filter_var($valor, FILTER_VALIDATE_INT, array(
			"options" => array("min_range" => 0)
		));

		return $valor === false ? $porDefecto : $valor;
	}

	/*=============================================
	MOSTRAR CATEGORÍAS
	=============================================*/

	static public function mdlMostrarCategorias($tabla, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}
		
		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR SUB-CATEGORÍAS
	=============================================*/

	static public function mdlMostrarSubCategorias($tabla, $item, $valor){

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

		$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

		$stmt -> execute();

		return $stmt -> fetchAll();

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR PRODUCTOS
	=============================================*/

	static public function mdlMostrarProductos($tabla, $ordenar, $item, $valor, $base, $tope, $modo){

		$ordenar = self::normalizarOrden($ordenar);
		$base = self::normalizarLimite($base, 0);
		$tope = self::normalizarLimite($tope, 12);

		if($modo === "Rand()"){
			$clausulaOrden = "RAND()";
		}else{
			$modo = self::normalizarModo($modo);
			$clausulaOrden = "$ordenar $modo";
		}

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT *FROM $tabla WHERE $item = :$item ORDER BY $clausulaOrden LIMIT $base, $tope");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetchAll();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT *FROM $tabla ORDER BY $clausulaOrden LIMIT $base, $tope");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}


	/*=============================================
	MOSTRAR INFOPRODUCTO
	=============================================*/

	static public function mdlMostrarInfoProducto($tabla, $item, $valor){

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

		$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

		$stmt -> execute();

		return $stmt -> fetch();

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	LISTAR PRODUCTOS
	=============================================*/

	static public function mdlListarProductos($tabla, $ordenar, $item, $valor){

		$ordenar = self::normalizarOrden($ordenar);

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item ORDER BY $ordenar DESC");
			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetchAll();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY $ordenar DESC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR BANNER
	=============================================*/

	static public function mdlMostrarBanner($tabla, $ruta){

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE ruta = :ruta");

		$stmt -> bindParam(":ruta", $ruta, PDO::PARAM_STR);

		$stmt -> execute();

		return $stmt -> fetch();

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	BUSCADOR
	=============================================*/

	static public function mdlBuscarProductos($tabla, $busqueda, $ordenar, $modo, $base, $tope){

		$ordenar = self::normalizarOrden($ordenar);
		$modo = self::normalizarModo($modo);
		$base = self::normalizarLimite($base, 0);
		$tope = self::normalizarLimite($tope, 12);
		$termino = "%".$busqueda."%";

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE ruta LIKE :busqueda OR titulo LIKE :busqueda OR titular LIKE :busqueda OR descripcion LIKE :busqueda ORDER BY $ordenar $modo LIMIT $base, $tope");

		$stmt -> bindParam(":busqueda", $termino, PDO::PARAM_STR);
		$stmt -> execute();

		return $stmt -> fetchAll();

	}

	/*=============================================
	LISTAR PRODUCTOS
	=============================================*/

	static public function mdlListarProductosBusqueda($tabla, $busqueda){

		$termino = "%".$busqueda."%";

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE ruta LIKE :busqueda OR titulo LIKE :busqueda OR titular LIKE :busqueda OR descripcion LIKE :busqueda");

		$stmt -> bindParam(":busqueda", $termino, PDO::PARAM_STR);
		$stmt -> execute();

		return $stmt -> fetchAll();

	}

	/*=============================================
	ACTUALIZAR VISTA PRODUCTO
	=============================================*/

	static public function mdlActualizarProducto($tabla, $item1, $valor1, $item2, $valor2){

		$stmt = Conexion::conectar()->prepare("UPDATE $tabla SET $item1 = :$item1 WHERE $item2 = :$item2");

		$stmt -> bindParam(":".$item1, $valor1, PDO::PARAM_STR);
		$stmt -> bindParam(":".$item2, $valor2, PDO::PARAM_STR);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;

	}

}