#!/usr/bin/env python3
import json
import os
from datetime import datetime

# Rutas absolutas (Ajustar si es necesario)
BASE_DIR = '/var/www/html/banco'
USERS_FILE = os.path.join(BASE_DIR, 'users/usuarios.json')
CONFIG_FILE = os.path.join(BASE_DIR, 'config.json')
ACCOUNTS_DIR = os.path.join(BASE_DIR, 'cuentas')

def cargar_json(ruta):
    if not os.path.exists(ruta):
        return None
    try:
        with open(ruta, 'r') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error leyendo {ruta}: {e}")
        return None

def guardar_json(ruta, data):
    try:
        with open(ruta, 'w') as f:
            json.dump(data, f, indent=4)
    except Exception as e:
        print(f"Error guardando {ruta}: {e}")

def procesar_cuentas():
    print(f"--- Inicio proceso: {datetime.now()} ---")
    
    # Cargar configuración
    config = cargar_json(CONFIG_FILE)
    if not config:
        print("Error: No se pudo cargar config.json")
        return

    tasa_ars = config.get('interes_mensual', 0.02)
    tasa_usd = config.get('interes_mensual_usd', 0.01)
    
    # Cargar usuarios
    data_users = cargar_json(USERS_FILE)
    if not data_users:
        print("Error: No se pudo cargar usuarios.json")
        return

    for user in data_users['users']:
        username = user['username'].lower()
        
        # --- PROCESAR ARS ---
        file_ars = os.path.join(ACCOUNTS_DIR, f"{username}.json")
        movs_ars = cargar_json(file_ars)
        
        if movs_ars and len(movs_ars) > 0:
            ultimo_total = movs_ars[-1].get('total_en_cuenta', 0)
            if ultimo_total > 0:
                interes = (ultimo_total * tasa_ars) / 30
                nuevo_total = ultimo_total + interes
                
                nuevo_mov = {
                    "fecha": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "tipo": "Rendimiento",
                    "monto": round(interes, 2),
                    "concepto": "Rendimiento diario",
                    "total_en_cuenta": round(nuevo_total, 2)
                }
                movs_ars.append(nuevo_mov)
                guardar_json(file_ars, movs_ars)
                print(f"ARS: {username} +${round(interes, 2)}")

        # --- PROCESAR USD ---
        file_usd = os.path.join(ACCOUNTS_DIR, f"{username}_usd.json")
        movs_usd = cargar_json(file_usd)
        
        # Solo procesar si el archivo existe y tiene movimientos
        if movs_usd and len(movs_usd) > 0:
            ultimo_total = movs_usd[-1].get('total_en_cuenta', 0)
            if ultimo_total > 0:
                interes = (ultimo_total * tasa_usd) / 30
                nuevo_total = ultimo_total + interes
                
                nuevo_mov = {
                    "fecha": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "tipo": "Rendimiento",
                    "monto": round(interes, 2),
                    "concepto": "Rendimiento diario",
                    "total_en_cuenta": round(nuevo_total, 2)
                }
                movs_usd.append(nuevo_mov)
                guardar_json(file_usd, movs_usd)
                print(f"USD: {username} +US${round(interes, 2)}")

if __name__ == "__main__":
    procesar_cuentas()