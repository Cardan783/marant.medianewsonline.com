import os
import random
from datetime import datetime, timedelta

# --- CONFIGURACIÓN ---
DIAS_A_GENERAR = 60

# --- FUNCIONES ---

def get_output_dir():
    """
    Detecta inteligentemente dónde guardar los archivos.
    """
    current_dir = os.getcwd()
    folder_name = os.path.basename(current_dir)
    
    # Caso 1: Ya estás dentro de la carpeta uploads
    if folder_name == "uploads":
        return "."
    
    # Caso 2: Estás en la raíz del proyecto
    target = os.path.join("Archivos_SDCards", "uploads")
    if not os.path.exists(target):
        try:
            os.makedirs(target)
        except OSError:
            pass 
    return target

def format_mac_filename(mac):
    """Convierte AA:00... a MAC=AA-00... para el nombre del archivo."""
    clean_mac = mac.replace(":", "-").upper()
    return f"MAC={clean_mac}_Registro_normal_de_operacion.txt"

def generate_daily_log(date):
    """Genera un bloque de texto con el NUEVO FORMATO solicitado."""
    
    # Generar valores aleatorios (Simulando sistema 24V como en tu ejemplo)
    temp_max = round(random.uniform(80, 96), 2)
    if random.random() > 0.85: temp_max = round(random.uniform(96, 105), 2)
        
    pres_max = round(random.uniform(3.5, 7.5), 2)
    pres_min = round(random.uniform(0.8, 2.5), 2)
    
    volt_max = round(random.uniform(24.5, 29.8), 2) 
    volt_min = round(random.uniform(21.0, 24.0), 2)
    
    # Alarmas configuradas (simuladas)
    alarm_temp = round(random.uniform(85.0, 105.0), 2)
    alarm_pres = round(random.uniform(1.5, 2.1), 2)
    alarm_volt_max = round(random.uniform(24.5, 30.5), 2)
    alarm_volt_min = round(random.uniform(20.0, 23.5), 2)

    # FORMATO EXACTO SOLICITADO
    log_entry = f"""[{date.strftime('%d-%m-%Y')}]
Alarmas establecidas:
Temperatura Crítica = {alarm_temp}
Presión Mínima = {alarm_pres}
Voltaje Máximo = {alarm_volt_max}
Voltaje Mínimo = {alarm_volt_min}
Máxima temperatura alcanzada: {temp_max}
Máxima presión alcanzada: {pres_max}
Mínima presión alcanzada: {pres_min}
Máximo voltaje alcanzado: {volt_max}
Mínimo voltaje alcanzado: {volt_min}
----------------------------------------
"""
    return log_entry

def main():
    print("--- Generador de Archivos Dummy para SAMPATV (Nuevo Formato) ---")
    
    # 1. Determinar directorio destino
    output_dir = get_output_dir()
    abs_output_dir = os.path.abspath(output_dir)
    print(f"📂 Directorio de destino detectado: {abs_output_dir}")
    
    # 2. Generar archivos para MACs terminadas en 12, 22, 32 ... 92
    count = 0
    for i in range(12, 93, 10):
        # Construir la MAC Address (ej: AA:00:00:00:00:12)
        mac_address = f"AA:00:00:00:00:{i}"
        
        filename = format_mac_filename(mac_address)
        filepath = os.path.join(output_dir, filename)
        
        content = ""
        start_date = datetime.now()
        
        # Generar fechas en orden cronológico (del pasado al presente)
        fechas = []
        for d in range(DIAS_A_GENERAR):
            fechas.append(start_date - timedelta(days=d))
        fechas.sort() # Ordenar ascendente

        for current_date in fechas:
            content += generate_daily_log(current_date)
            
        try:
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(content)
            print(f"✅ Generado: {filename}")
            count += 1
        except IOError as e:
            print(f"❌ Error al escribir {filename}: {e}")

    print(f"\n🎉 Proceso completado. Se crearon {count} archivos.")
    print(f"👉 Verifica la carpeta: {abs_output_dir}")

if __name__ == "__main__":
    main()
