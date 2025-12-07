import os
import sys

class ConfigManager:
    """
    Load and manage dotenv-style configuration.
    Supports:
      - direct filepath given to constructor
      - CASHCUE_CONFIG_FILE environment variable
      - default config file at /etc/cashcue/cashcue.conf
    """

    def __init__(self, filepath=None):
        self.config = {}

        # 1️⃣ Priorité absolue : chemin passé explicitement au constructeur
        if filepath:
            if not os.path.exists(filepath):
                print(f"[ERROR] Config file not found: {filepath}")
                sys.exit(1)
            self.load(filepath)
            return

        # 2️⃣ Priorité secondaire : variable d'environnement CASHCUE_CONFIG_FILE
        env_file = os.environ.get("CASHCUE_CONFIG_FILE")
        if env_file:
            if not os.path.exists(env_file):
                print(f"[ERROR] Config file not found: {env_file}")
                sys.exit(1)
            self.load(env_file)
            return

        # 3️⃣ Fallback PROD : fichier par défaut
        default_file = "/etc/cashcue/cashcue.conf"
        if os.path.exists(default_file):
            self.load(default_file)
            return

        # 4️⃣ Rien trouvé → erreur explicite
        print("[ERROR] No configuration file could be found.")
        print("        Provide one of the following:")
        print("        - Constructor argument: ConfigManager('/path/to/config')")
        print("        - Environment variable: CASHCUE_CONFIG_FILE=/path")
        print("        - Default: /etc/cashcue/cashcue.conf")
        sys.exit(1)

    def load(self, filepath):
        with open(filepath, "r") as f:
            for line in f:
                line = line.strip()

                # Skip comments and empty lines
                if not line or line.startswith("#"):
                    continue

                if "=" in line:
                    key, value = line.split("=", 1)
                    key = key.strip()
                    value = value.strip()

                    # Remove inline comments (only if not inside quotes)
                    if value.startswith(("'", '"')):
                        # Do NOT split on '#'
                        pass
                    else:
                        value = value.split("#", 1)[0].strip()

                    # Remove surrounding quotes if any
                    if ((value.startswith("'") and value.endswith("'")) or
                        (value.startswith('"') and value.endswith('"'))):
                        value = value[1:-1]

                    self.config[key] = value

    def get(self, key, default=None):
        return self.config.get(key, default)

    def get_int(self, key, default=0):
        try:
            return int(self.config.get(key, default))
        except ValueError:
            return default

    def get_float(self, key, default=0.0):
        try:
            return float(self.config.get(key, default))
        except ValueError:
            return default

    def get_bool(self, key, default=False):
        val = self.config.get(key, str(default)).lower()
        return val in ("true", "1", "yes")
