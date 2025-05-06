from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import (
    TimeoutException, 
    NoSuchElementException, 
    StaleElementReferenceException,
    WebDriverException,
    InvalidSessionIdException
)
from selenium.webdriver.common.by import By
from selenium.common.exceptions import NoSuchFrameException
import time
import datetime
import argparse
import logging
import traceback
import sys
# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)

logger = logging.getLogger(__name__)

def wait_and_click(driver, by, selector, timeout=20, retry_count=3):
    """
    Espera a que un elemento sea clickeable y luego hace clic en él con reintentos.
    
    Args:
        driver: Instancia del WebDriver
        by: Método de localización (By.ID, By.XPATH, etc.)
        selector: Selector del elemento
        timeout: Tiempo máximo de espera (en segundos)
        retry_count: Número de reintentos
        
    Returns:
        True si el clic fue exitoso, False en caso contrario
    """
    for attempt in range(retry_count):
        try:
            element = WebDriverWait(driver, timeout).until(
                EC.element_to_be_clickable((by, selector))
            )
            # Hacer scroll al elemento para asegurar que sea visible
            driver.execute_script("arguments[0].scrollIntoView(true);", element)
            time.sleep(0.5)  # Pequeña pausa después del scroll
            element.click()
            logger.info(f"✅ Clic exitoso en {selector}")
            return True
        except Exception as e:
            logger.warning(f"⚠️ Intento {attempt+1}/{retry_count} fallido para clic en {selector}: {str(e)}")
            time.sleep(2)  # Esperar antes de reintentar
            
            # Intentar refrescar el elemento si está obsoleto
            if isinstance(e, StaleElementReferenceException):
                logger.info("Elemento obsoleto, refrescando página...")
                try:
                    driver.refresh()
                    time.sleep(3)
                except:
                    pass
    
    logger.error(f"❌ No se pudo hacer clic en {selector} después de {retry_count} intentos")
    return False

def wait_for_element(driver, by, selector, timeout=20, retry_count=3):
    """
    Espera a que un elemento esté presente con reintentos.
    
    Returns:
        El elemento si se encuentra, None en caso contrario
    """
    for attempt in range(retry_count):
        try:
            element = WebDriverWait(driver, timeout).until(
                EC.presence_of_element_located((by, selector))
            )
            return element
        except Exception as e:
            logger.warning(f"⚠️ Intento {attempt+1}/{retry_count} fallido encontrando {selector}: {str(e)}")
            time.sleep(2)
    
    logger.error(f"❌ No se pudo encontrar el elemento {selector} después de {retry_count} intentos")
    return None

def recuperar_navegacion(driver, intentos=3):
    """
    Intenta recuperar la navegación cuando hay errores.
    """
    logger.info("Intentando recuperar la navegación...")
    
    for intento in range(intentos):
        try:
            # Primero intentar usar los botones VOLVER y CANCEL si están disponibles
            try:
                volver_elements = driver.find_elements(By.ID, "VOLVER")
                if volver_elements and len(volver_elements) > 0:
                    logger.info("Encontrado botón VOLVER, haciendo clic...")
                    volver_elements[0].click()
                    time.sleep(3)
                    
                    cancel_elements = driver.find_elements(By.ID, "CANCEL")
                    if cancel_elements and len(cancel_elements) > 0:
                        logger.info("Encontrado botón CANCEL, haciendo clic...")
                        cancel_elements[0].click()
                        time.sleep(3)
                        logger.info("Recuperación exitosa usando botones")
                        return True
            except Exception as e:
                logger.warning(f"Error al intentar usar botones: {str(e)}")
            
            # Si los botones no funcionaron, intentar navegar hacia atrás
            logger.info("Intentando recuperar con navegación back()")
            driver.back()
            time.sleep(3)
            
            # Verificar si estamos en la página principal comprobando algún elemento clave
            try:
                if driver.find_elements(By.ID, "vCRITERIO"):
                    logger.info("Recuperación exitosa mediante back()")
                    return True
            except:
                pass
                
            # Último recurso: recargar la página principal
            if intento == intentos - 1:
                try:
                    current_url = driver.current_url
                    logger.info(f"Intentando recargar la URL: {current_url}")
                    driver.get(current_url)
                    time.sleep(5)
                    
                    # Verificar si se cargó correctamente
                    if driver.find_elements(By.ID, "vCRITERIO"):
                        logger.info("Recuperación exitosa mediante recarga")
                        return True
                except Exception as reload_error:
                    logger.error(f"Error al recargar página: {str(reload_error)}")
        
        except InvalidSessionIdException:
            logger.error("Sesión de Selenium inválida. No se puede recuperar.")
            return False
        except Exception as e:
            logger.error(f"Error durante recuperación (intento {intento+1}): {str(e)}")
        
        time.sleep(2)
    
    logger.error("❌ No se pudo recuperar la navegación después de varios intentos")
    return False

def reiniciar_navegador(opciones_chrome):
    """
    Reinicia el navegador cuando hay problemas graves.
    """
    logger.info("🔄 Reiniciando el navegador...")
    try:
        # Crear un nuevo driver
        nuevo_driver = webdriver.Chrome(options=opciones_chrome)
        nuevo_driver.maximize_window()
        
        logger.info("✅ Navegador reiniciado correctamente")
        return nuevo_driver
    except Exception as e:
        logger.error(f"❌ Error al reiniciar navegador: {str(e)}")
        return None

# Función para registrar en archivo log
def log_descarga(nombre_archivo):
    """
    Registra la descarga exitosa en un archivo de log
    """
    log_file = "descargas_lab_nancy.txt"
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    with open(log_file, "a", encoding="utf-8") as f:
        f.write(f"{timestamp} - Descarga exitosa: {nombre_archivo}\n")
    
    logger.info(f"📝 Registro guardado en {log_file}")
#proceso para cambiar al iframe
def cambiar_a_iframe(driver, index):
    """
    Cambia correctamente al iframe que contiene el contenido de resultados.
    """
    try:
        logger.info("Intentando cambiar al iframe...")
        iframes = driver.find_elements(By.TAG_NAME, "iframe")
        if len(iframes) >= 2:
            iframe = iframes[1]  # índice 1 para el segundo iframe
            driver.switch_to.frame(iframe)
            logger.info("Cambiado exitosamente al iframe")
            return True
        else:
            logger.info(f"No se encontraron suficientes iframes. Solo hay {len(iframes)} disponibles.")
            # Intentar con el primer iframe si solo hay uno
            if len(iframes) == 1:
                driver.switch_to.frame(iframes[0])
                logger.info("Cambiado al único iframe disponible")
                return True
            return False
    except Exception as e:
        logger.error(f"Error al cambiar al iframe: {e}")
        return False
#Proceso para descargar resultados
def descargar_resultado(driver, index, total):
    """
    Función para descargar un resultado individual con mejor manejo de errores.
    
    Returns:
        True si la descarga fue exitosa, False en caso contrario
    """
    logger.info(f"✅ Enlace Ver {index+1}/{total}: Iniciando secuencia de descarga")
    
    try:
        # Intentar obtener el nombre/identificador del resultado para el log
        try:
            # Buscar el identificador (puede ser un ID de paciente, número de muestra, etc.)
            # Ajusta este selector según la estructura real de la página
            identificadores = driver.find_elements(By.XPATH, f"//tr[{index+1}]/td[contains(@id, '_CTLCOD') or contains(@id, '_CTLID')]")
            nombre_archivo = "desconocido"
            if identificadores and len(identificadores) > 0:
                nombre_archivo = identificadores[0].text.strip()
        except:
            nombre_archivo = f"resultado_{index+1}"
        
        # Volver a obtener los enlaces por si el DOM se recarga
        ver_links = driver.find_elements(By.XPATH, "//span[starts-with(@id, 'span_CTLVER_')]/a")
        if index >= len(ver_links):
            logger.error(f"❌ Índice {index} fuera de rango. Solo hay {len(ver_links)} enlaces disponibles")
            return False
        
        # 1. Clic en "Ver"
        try:
            ver_links[index].click()
            logger.info("  ↳ 1/5: Clic en enlace 'Ver' completado")
            time.sleep(3)  # Espera para que cargue la página
        except StaleElementReferenceException:
            # Si el elemento está obsoleto, refrescar la lista
            ver_links = driver.find_elements(By.XPATH, "//span[starts-with(@id, 'span_CTLVER_')]/a")
            if index >= len(ver_links):
                logger.error("❌ Enlaces 'Ver' ya no disponibles después de refresco")
                return False
            ver_links[index].click()
            logger.info("  ↳ 1/5: Clic en enlace 'Ver' completado (después de refrescar)")
            time.sleep(6)
        
        # IMPORTANTE: Cambiar al iframe correcto
        logger.info("  ↳ Cambiando al iframe...")
        if not cambiar_a_iframe(driver, 1):
            logger.error("❌ No se pudo cambiar al iframe")
            return False
        
        # 2. Clic en "Descargar Resultados" (IMPRIMIR)
        try:
            imprimir_btn = WebDriverWait(driver, 15).until(
                EC.element_to_be_clickable((By.ID, "IMPRIMIR"))
            )
            # Hacer scroll hasta el botón para asegurar que sea visible
            driver.execute_script("arguments[0].scrollIntoView(true);", imprimir_btn)
            time.sleep(1)
            imprimir_btn.click()
            logger.info("  ↳ 2/5: Clic en botón 'Descargar Resultados' (IMPRIMIR) completado")
            time.sleep(3)
        except Exception as e:
            cancel_btn = WebDriverWait(driver, 15).until(
                EC.element_to_be_clickable((By.ID, "CANCEL"))
            )
            driver.execute_script("arguments[0].scrollIntoView(true);", cancel_btn)
            time.sleep(1)
            cancel_btn.click()
            logger.info("  ↳ 3/3: Clic en segundo botón 'Volver' (CANCEL) completado")
            time.sleep(3)
            
            # Volver al contenido principal
            try:
                driver.switch_to.default_content()
                logger.info("  ↳ Volviendo al contenido principal")
            except Exception as e:
                logger.error(f"❌ Error al volver al contenido principal: {str(e)}")
                # No retornamos False aquí, porque la operación principal ya se completó
            
            # Registrar la descarga en el log
            log_descarga(nombre_archivo)
            
            logger.info(f"  ✅ Descarga del resultado {index+1}/{total} completada con éxito")
            return True
    
        # 3. Clic en "Descargar"
        descargar_btn = WebDriverWait(driver, 15).until(
            EC.element_to_be_clickable((By.ID, "DESCARGAR"))
        )
        driver.execute_script("arguments[0].scrollIntoView(true);", descargar_btn)
        time.sleep(1)
        descargar_btn.click()
        logger.info("  ↳ 3/5: Clic en botón 'Descargar' completado")
        time.sleep(5)  # Tiempo extra para asegurar que comience la descarga
        
        # Manejar posible ventana emergente
        logger.info("  ↳ Esperando a que se procese la ventana emergente de descarga...")
        time.sleep(5)
        
        # Verificar si hay alertas JavaScript y aceptarlas
        try:
            alert = driver.switch_to.alert
            logger.info(f"  ↳ Alerta detectada: '{alert.text}'. Aceptando...")
            alert.accept()
            time.sleep(2)
        except:
            logger.info("  ↳ No se detectaron alertas JavaScript")
        
        # 4. Clic en el primer "Volver" (VOLVER)
        volver_btn = WebDriverWait(driver, 15).until(
            EC.element_to_be_clickable((By.ID, "VOLVER"))
        )
        driver.execute_script("arguments[0].scrollIntoView(true);", volver_btn)
        time.sleep(1)
        volver_btn.click()
        logger.info("  ↳ 4/5: Clic en primer botón 'Volver' (VOLVER) completado")
        time.sleep(3)
        
        # 5. Clic en el segundo "Volver" (CANCEL)
        cancel_btn = WebDriverWait(driver, 15).until(
            EC.element_to_be_clickable((By.ID, "CANCEL"))
        )
        driver.execute_script("arguments[0].scrollIntoView(true);", cancel_btn)
        time.sleep(1)
        cancel_btn.click()
        logger.info("  ↳ 5/5: Clic en segundo botón 'Volver' (CANCEL) completado")
        time.sleep(3)
        
        # Volver al contenido principal
        try:
            driver.switch_to.default_content()
            logger.info("  ↳ Volviendo al contenido principal")
        except Exception as e:
            logger.error(f"❌ Error al volver al contenido principal: {str(e)}")
            # No retornamos False aquí, porque la operación principal ya se completó
        
        # Registrar la descarga en el log
        log_descarga(nombre_archivo)
        
        logger.info(f"  ✅ Descarga del resultado {index+1}/{total} completada con éxito")
        return True
        
    except InvalidSessionIdException as e:
        logger.error(f"❌ Sesión inválida: {str(e)}")
        logger.error("❌ Es necesario reiniciar el navegador")
        return False
    except Exception as e:
        logger.error(f"❌ Error en resultado {index+1}/{total}: {str(e)}")
        logger.error(f"❌ Detalle del error: {traceback.format_exc()}")
        return False
#proceso para pasar a la siguiente página
def pasar_pagina(driver, timeout=15, max_intentos=5):
    """
    Intenta hacer clic en el botón "Siguiente" para navegar a la siguiente página de resultados.
    
    Returns:
        True si se pudo hacer clic en el botón, False en caso contrario
    """
    logger.info("🔄 Intentando pasar a la siguiente página...")
    
    for intento in range(max_intentos):
        try:
            # Intentar encontrar el botón de siguiente página por su clase CSS
            siguiente_btn = WebDriverWait(driver, timeout).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, ".PagingButtonsNext"))
            )
            
            # Hacer scroll al botón para asegurar que sea visible
            driver.execute_script("arguments[0].scrollIntoView(true);", siguiente_btn)
            time.sleep(1)
            
            # Verificar si el botón está habilitado
            if siguiente_btn.is_enabled():
                siguiente_btn.click()
                logger.info("✅ Navegación a la siguiente página exitosa")
                time.sleep(5)  # Esperar a que cargue la nueva página
                return True
            else:
                logger.info("⚠️ Botón 'Siguiente' está deshabilitado - posiblemente es la última página")
                return False
                
        except TimeoutException:
            # Intentar con un selector XPath alternativo si el CSS no funciona
            try:
                siguiente_btn = WebDriverWait(driver, timeout).until(
                    EC.element_to_be_clickable((By.XPATH, "//button[contains(@class, 'PagingButtonsNext')]"))
                )
                driver.execute_script("arguments[0].scrollIntoView(true);", siguiente_btn)
                time.sleep(1)
                siguiente_btn.click()
                logger.info("✅ Navegación a la siguiente página exitosa (usando XPath)")
                time.sleep(5)
                return True
            except:
                pass
                
            logger.warning(f"⚠️ No se encontró el botón 'Siguiente' (intento {intento+1}/{max_intentos})")
            
        except Exception as e:
            logger.warning(f"⚠️ Error al intentar navegar: {str(e)} (intento {intento+1}/{max_intentos})")
            
        time.sleep(2)  # Esperar antes de reintentar
        
    logger.error("❌ No se pudo navegar a la siguiente página después de varios intentos")
    return False

#proceso para configurar el sistema
def configurar_sistema(username="1234", password="1234", fecha_desde=None, fecha_hasta=None, descargar_resultados=True, max_reintentos=3, headless=False):
    """
    Configura el sistema de laboratorio con las fechas especificadas y opcionalmente descarga los resultados.
    
    Args:
        username: Número de documento/usuario (default: "1234")
        password: Contraseña (default: "1234")
        fecha_desde: Fecha inicial en formato DD/MM/AAAA (default: primero del mes actual)
        fecha_hasta: Fecha final en formato DD/MM/AAAA (default: último día del mes actual)
        descargar_resultados: Si es True, descarga automáticamente todos los resultados disponibles
        max_reintentos: Número máximo de reintentos para descargar resultados
        headless: Si es True, ejecuta Chrome en modo headless (sin interfaz gráfica)
    """
    # Configurar fechas por defecto si no se proporcionan
    if not fecha_desde or not fecha_hasta:
        hoy = datetime.datetime.now()
        primer_dia = datetime.datetime(hoy.year, hoy.month, 1)
        
        # Calcular el último día del mes
        if hoy.month == 12:
            ultimo_dia = datetime.datetime(hoy.year + 1, 1, 1) - datetime.timedelta(days=1)
        else:
            ultimo_dia = datetime.datetime(hoy.year, hoy.month + 1, 1) - datetime.timedelta(days=1)
        
        fecha_desde = fecha_desde or primer_dia.strftime("%d/%m/%Y")
        fecha_hasta = fecha_hasta or ultimo_dia.strftime("%d/%m/%Y")
    
    logger.info(f"Configurando con las siguientes fechas:")
    logger.info(f"Desde: {fecha_desde}")
    logger.info(f"Hasta: {fecha_hasta}")
    
    # Configurar opciones de Chrome
    chrome_options = Options()
    chrome_options.add_argument("--disable-application-cache")
    chrome_options.add_argument("--disable-extensions")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-gpu")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--disk-cache-size=1")
    
    # Agregar opción para modo headless si se solicita
    if headless:
        chrome_options.add_argument("--headless")
        logger.info("Modo headless activado")
    
    # Mejorar la gestión de descargas
    chrome_options.add_experimental_option("prefs", {
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": True
    })
    
    # Variable para control de reintentos
    intentos_login = 0
    MAX_INTENTOS_LOGIN = 3
    
    driver = None
    
    try:
        # Iniciar el driver
        driver = webdriver.Chrome(options=chrome_options)
        driver.maximize_window()  # Maximizar la ventana para asegurar que todos los elementos sean visibles
        
        # Espera más larga para cargas lentas
        wait = WebDriverWait(driver, 20)  # Espera explícita de hasta 20 segundos
        
        # Bucle para reintentar el login si falla
        while intentos_login < MAX_INTENTOS_LOGIN:
            try:
                # Abrir la página
                logger.info("Abriendo la página de login...")
                driver.get("")
                time.sleep(5)  # Espera inicial para que la página cargue completamente
                
                # Función para intentar seleccionar una opción con reintento
                def select_option_with_retry(select_id, option_value, max_attempts=5):
                    for attempt in range(max_attempts):
                        try:
                            logger.info(f"Intentando seleccionar {option_value} en {select_id} (intento {attempt+1})")
                            select_element = wait.until(EC.presence_of_element_located((By.ID, select_id)))
                            select = Select(select_element)
                            select.select_by_value(option_value)
                            time.sleep(1)  # Pequeña pausa después de seleccionar
                            return True
                        except Exception as e:
                            logger.warning(f"Error al seleccionar opción: {e}")
                            time.sleep(2)  # Esperar antes de reintentar
                    return False
                
                # Seleccionar "Empresa" en el primer select
                logger.info("Seleccionando tipo 'Empresa'...")
                if not select_option_with_retry("vTIPO", "E"):
                    raise Exception("No se pudo seleccionar la opción 'Empresa'")
                
                # Seleccionar "NIT" en el segundo select
                logger.info("Seleccionando tipo de documento 'NIT'...")
                if not select_option_with_retry("vTIPODCTO_COD", "NI"):
                    raise Exception("No se pudo seleccionar la opción 'NIT'")
                
                # Escribir el número de documento
                logger.info(f"Ingresando número de documento: {username}")
                try:
                    num_doc_input = wait.until(EC.presence_of_element_located((By.ID, "vNUM_DOC")))
                    num_doc_input.clear()
                    num_doc_input.send_keys(username)
                    time.sleep(1)
                except Exception as e:
                    logger.error(f"Error al ingresar número de documento: {e}")
                    raise
                
                # Hacer clic en el botón "Siguiente"
                logger.info("Haciendo clic en 'Siguiente'...")
                try:
                    siguiente_btn = wait.until(EC.element_to_be_clickable((By.ID, "SIGUIENTE")))
                    siguiente_btn.click()
                    time.sleep(3)  # Esperar a que cargue la página de contraseña
                except Exception as e:
                    logger.error(f"Error al hacer clic en 'Siguiente': {e}")
                    raise
                
                # Ingresar la contraseña
                logger.info(f"Ingresando contraseña...")
                try:
                    password_input = wait.until(EC.presence_of_element_located((By.ID, "vPASSWORD")))
                    password_input.clear()
                    password_input.send_keys(password)
                    time.sleep(1)
                except Exception as e:
                    logger.error(f"Error al ingresar contraseña: {e}")
                    raise
                
                # Hacer clic en el botón "Ingresar"
                logger.info("Haciendo clic en 'Ingresar'...")
                try:
                    ingresar_btn = wait.until(EC.element_to_be_clickable((By.ID, "INGRESAR")))
                    ingresar_btn.click()
                    time.sleep(5)  # Esperar a que cargue la página principal
                except Exception as e:
                    logger.error(f"Error al hacer clic en 'Ingresar': {e}")
                    raise
                
                # Verificar si el login fue exitoso buscando un elemento de la página principal
                try:
                    criterio_select = wait.until(EC.presence_of_element_located((By.ID, "vCRITERIO")))
                    logger.info("✅ Login exitoso")
                    break  # Si llegamos aquí, salimos del bucle de intentos de login
                except TimeoutException:
                    logger.warning(f"⚠️ Login fallido (intento {intentos_login+1}/{MAX_INTENTOS_LOGIN})")
                    intentos_login += 1
                    if intentos_login >= MAX_INTENTOS_LOGIN:
                        raise Exception("No se pudo iniciar sesión después de varios intentos")
                    continue  # Intentar de nuevo
                
            except Exception as e:
                logger.error(f"Error durante el login (intento {intentos_login+1}): {str(e)}")
                intentos_login += 1
                if intentos_login >= MAX_INTENTOS_LOGIN:
                    raise
                time.sleep(5)  # Esperar antes de reintentar
                continue
        
        # Seleccionar "RANGO FECHAS"
        logger.info("Seleccionando criterio 'RANGO FECHAS'...")
        if not select_option_with_retry("vCRITERIO", "1"):
            raise Exception("No se pudo seleccionar la opción 'RANGO FECHAS'")
        
        # Establecer la fecha "Desde"
        logger.info(f"Estableciendo fecha Desde: {fecha_desde}")
        try:
            driver.execute_script(f"""
                const desde = document.getElementById('vDESDEFEC');
                desde.value = '{fecha_desde}';
                desde.dispatchEvent(new Event('change', {{ bubbles: true }}));
                desde.dispatchEvent(new Event('blur', {{ bubbles: true }}));
            """)
            time.sleep(1)
        except Exception as e:
            logger.error(f"Error al establecer fecha Desde: {e}")
            raise
        
        # Establecer la fecha "Hasta"
        logger.info(f"Estableciendo fecha Hasta: {fecha_hasta}")
        try:
            driver.execute_script(f"""
                const hasta = document.getElementById('vHASFEC');
                hasta.value = '{fecha_hasta}';
                hasta.dispatchEvent(new Event('change', {{ bubbles: true }}));
                hasta.dispatchEvent(new Event('blur', {{ bubbles: true }}));
            """)
            time.sleep(1)
        except Exception as e:
            logger.error(f"Error al establecer fecha Hasta: {e}")
            raise
        
        # Hacer clic en el botón "Buscar"
        logger.info("Haciendo clic en 'Buscar'...")
        try:
            buscar_btn = wait.until(EC.element_to_be_clickable((By.ID, "IMAGE5")))
            buscar_btn.click()
            time.sleep(5)  # Esperar a que se carguen los resultados
        except Exception as e:
            logger.error(f"Error al hacer clic en 'Buscar': {e}")
            raise
            
            # Descargar resultados si se ha solicitado
        if descargar_resultados:
                logger.info("\n🔄 Iniciando descarga de resultados...")
                
                # Variables para seguimiento global
                pagina_actual = 1
                total_resultados_descargados = 0
                hay_mas_paginas = True
                
                # Procesar todas las páginas disponibles
                while hay_mas_paginas:
                    try:
                        logger.info(f"\n📄 Procesando página {pagina_actual}...")
                        
                        # Esperar a que los enlaces "Ver" estén disponibles
                        WebDriverWait(driver, 15).until(
                            EC.presence_of_element_located((By.XPATH, "//span[starts-with(@id, 'span_CTLVER_')]/a"))
                        )
                        
                        # Buscar todos los enlaces "Ver" en la página actual
                        ver_links = driver.find_elements(By.XPATH, "//span[starts-with(@id, 'span_CTLVER_')]/a")
                        total_resultados_pagina = len(ver_links)
                        logger.info(f"Se encontraron {total_resultados_pagina} resultados en la página {pagina_actual}")
                        
                        if total_resultados_pagina == 0:
                            logger.warning(f"⚠️ No se encontraron resultados en la página {pagina_actual}")
                            # Intentar pasar a la siguiente página
                            hay_mas_paginas = pasar_pagina(driver)
                            if hay_mas_paginas:
                                pagina_actual += 1
                                continue
                            else:
                                break
                        
                        # Iterar sobre cada resultado en la página actual
                        index = 0
                        resultados_descargados_pagina = 0
                        intentos_globales = 0
                        
                        while index < total_resultados_pagina and intentos_globales < max_reintentos:
                            try:
                                # Intentar descargar el resultado actual
                                exito = descargar_resultado(driver, index, total_resultados_pagina)
                                
                                if exito:
                                    # Si tuvimos éxito, avanzamos al siguiente resultado
                                    resultados_descargados_pagina += 1
                                    total_resultados_descargados += 1
                                    index += 1
                                    intentos_globales = 0  # Resetear contador de intentos globales
                                else:
                                    # Si falló, incrementar contador de intentos globales
                                    intentos_globales += 1
                                    logger.warning(f"⚠️ Reintento global {intentos_globales}/{max_reintentos}")
                                    
                                    # Intentar recuperar la navegación
                                    if not recuperar_navegacion(driver):
                                        # Si la recuperación falló, intentar reiniciar el navegador
                                        if intentos_globales >= 2:  # Solo reiniciar después de algunos intentos
                                            logger.warning("⚠️ Intentando reiniciar el navegador...")
                                            
                                            # Cerrar el driver actual
                                            try:
                                                driver.quit()
                                            except:
                                                pass
                                            
                                            # Reiniciar el navegador y todo el proceso
                                            logger.info("🔄 Reiniciando todo el proceso desde cero...")
                                            return configurar_sistema(
                                                username=username, 
                                                password=password, 
                                                fecha_desde=fecha_desde, 
                                                fecha_hasta=fecha_hasta, 
                                                descargar_resultados=descargar_resultados,
                                                max_reintentos=max_reintentos
                                            )
                                    
                                    # Si recuperamos la navegación, intentar continuar desde donde estábamos
                                    try:
                                        # Verificar si todavía estamos en la página de resultados
                                        if not driver.find_elements(By.XPATH, "//span[starts-with(@id, 'span_CTLVER_')]/a"):
                                            # Si no encontramos la lista, volver a buscar
                                            logger.info("Volviendo a hacer clic en 'Buscar'...")
                                            buscar_btn = WebDriverWait(driver, 15).until(
                                                EC.element_to_be_clickable((By.ID, "IMAGE5"))
                                            )
                                            buscar_btn.click()
                                            time.sleep(5)
                                            
                                            # Si estábamos en una página diferente a la primera, 
                                            # necesitamos navegar hasta esa página de nuevo
                                            for i in range(1, pagina_actual):
                                                logger.info(f"Navegando de nuevo a la página {i+1}...")
                                                if not pasar_pagina(driver):
                                                    logger.error(f"No se pudo volver a la página {pagina_actual}")
                                                    break
                                                time.sleep(3)
                                    except:
                                        logger.error("Error al intentar volver a la página de resultados")
                            
                            except Exception as e:
                                logger.error(f"Error no manejado: {str(e)}")
                                intentos_globales += 1
                                
                                if intentos_globales >= max_reintentos:
                                    logger.error(f"Se alcanzó el máximo de reintentos ({max_reintentos})")
                                    break
                                
                                # Intentar recuperación
                                recuperar_navegacion(driver)
                        
                        logger.info(f"✅ Página {pagina_actual} completada. Se descargaron {resultados_descargados_pagina} de {total_resultados_pagina} resultados.")
                        
                        # Intentar pasar a la siguiente página
                        hay_mas_paginas = pasar_pagina(driver)
                        if hay_mas_paginas:
                            pagina_actual += 1
                        else:
                            logger.info("Se han procesado todas las páginas disponibles.")
                            break
                    
                    except Exception as e:
                        logger.error(f"⚠️ Error al procesar la página {pagina_actual}: {str(e)}")
                        logger.error(traceback.format_exc())
                        
                        # Intentar recuperar y continuar con la siguiente página
                        if recuperar_navegacion(driver):
                            hay_mas_paginas = pasar_pagina(driver)
                            if hay_mas_paginas:
                                pagina_actual += 1
                            else:
                                break
                        else:
                            # Si no podemos recuperar, terminamos
                            logger.error("No se puede continuar después del error.")
                            break
                
                        logger.info(f"\n✅ Proceso de descarga completado. Se descargaron un total de {total_resultados_descargados} resultados en {pagina_actual} páginas.")

        logger.info("\n✅ Configuración completada correctamente. El navegador permanecerá abierto.")
        logger.info("📌 IMPORTANTE: No cierre esta ventana de comando mientras desee mantener el navegador abierto.")
        logger.info("📌 Para cerrar el navegador, cierre esta ventana o presione Ctrl+C.")
        
        # Mantener el script en ejecución indefinidamente
        while True:
            time.sleep(10)  # Verificar cada 10 segundos si el navegador sigue abierto
            try:
                # Simplemente verificar si el driver sigue activo
                current_url = driver.current_url
            except:
                logger.info("Navegador cerrado. Finalizando script.")
                break
        
    except KeyboardInterrupt:
        logger.info("\nScript interrumpido por el usuario. Finalizando...")
    except Exception as e:
        logger.error(f"\n❌ Error durante la configuración: {e}")
        logger.error(traceback.format_exc())
        input("\nPresione Enter para finalizar...")
    finally:
        # Cerrar el driver si todavía está abierto
        if driver:
            try:
                driver.quit()
                logger.info("Navegador cerrado correctamente")
            except:
                pass

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Configurar fechas en el sistema de laboratorio')
    parser.add_argument('--username', type=str, default="-1", help='Número de documento/usuario')
    parser.add_argument('--password', type=str, default="-1", help='Contraseña')
    parser.add_argument('--desde', type=str, help='Fecha desde (DD/MM/AAAA)')
    parser.add_argument('--hasta', type=str, help='Fecha hasta (DD/MM/AAAA)')
    parser.add_argument('--no-descargar', action='store_true', help='No descargar resultados automáticamente')
    parser.add_argument('--reintentos', type=int, default=3, help='Número máximo de reintentos para descargar resultados')
    parser.add_argument('--headless', action='store_true', help='Ejecutar en modo headless (sin interfaz gráfica)')
    
    args = parser.parse_args()
    
    configurar_sistema(
        username=args.username,
        password=args.password,
        fecha_desde=args.desde,
        fecha_hasta=args.hasta,
        descargar_resultados=not args.no_descargar,
        max_reintentos=args.reintentos,
        headless=args.headless
    )