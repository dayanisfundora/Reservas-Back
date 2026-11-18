// Lanzador del sistema Reservas.
// Se compila con el csc.exe de .NET Framework 4 que trae Windows (C# 5): no usar sintaxis más nueva.

using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace Reservas
{
    internal static class Program
    {
        public const string ArgumentoReinicio = "--reiniciar";

        [STAThread]
        private static void Main(string[] args)
        {
            bool esPrimeraInstancia;
            using (Mutex mutex = new Mutex(true, "Local\\ReservasLanzador", out esPrimeraInstancia))
            {
                // Al reiniciar, la instancia anterior todavía se está cerrando: esperar a que libere el mutex
                if (!esPrimeraInstancia && Array.IndexOf(args, ArgumentoReinicio) >= 0)
                {
                    esPrimeraInstancia = EsperarMutex(mutex);
                }

                if (!esPrimeraInstancia)
                {
                    Lanzador.AbrirNavegadorDeInstanciaActiva();
                    return;
                }

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new Lanzador());
                mutex.ReleaseMutex();
            }
        }

        private static bool EsperarMutex(Mutex mutex)
        {
            try
            {
                return mutex.WaitOne(TimeSpan.FromSeconds(90));
            }
            catch (AbandonedMutexException)
            {
                return true;
            }
        }
    }

    internal sealed class Lanzador : ApplicationContext
    {
        private const string NombreSistema = "Reservas";
        private const int PuertoSistemaPorDefecto = 8093;
        private const int PuertoMysqlPorDefecto = 3320;

        // Puerto del MySQL de desarrollo (Laragon/XAMPP): el paquete nunca debe conectarse a él
        private const int PuertoMysqlDesarrollo = 3306;

        // Nombres de base de datos reservados para la instalación de desarrollo con datos reales
        private static readonly string[] BasesReservadas = { "reservas_app" };

        private static readonly string[] ClavesDelLanzador = { "PUERTO_SISTEMA", "PUERTO_MYSQL" };

        private readonly string carpetaPrograma;
        private readonly string carpetaApp;
        private readonly string carpetaPhp;
        private readonly string carpetaMysql;
        private readonly string carpetaDatos;
        private readonly string carpetaLogs;
        private readonly string archivoConfiguracion;
        private readonly string archivoLog;

        private Dictionary<string, string> configuracion;
        private string textoConfiguracion;
        private FileSystemWatcher vigilanteConfiguracion;
        private System.Windows.Forms.Timer esperaCambiosConfiguracion;
        private int puertoSistema;
        private int puertoMysql;

        private Process procesoMysql;
        private Process procesoPhp;
        private NotifyIcon iconoBandeja;
        private VentanaInicio ventanaInicio;
        private SynchronizationContext contextoInterfaz;
        private int idHiloInterfaz;
        private bool cerrando;

        public Lanzador()
        {
            carpetaPrograma = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\');
            carpetaApp = Path.Combine(carpetaPrograma, "app");
            carpetaPhp = Path.Combine(carpetaPrograma, "php");
            carpetaMysql = Path.Combine(carpetaPrograma, "mysql");
            carpetaDatos = CarpetaDatosUsuario();
            carpetaLogs = Path.Combine(carpetaDatos, "logs");
            archivoConfiguracion = Path.Combine(carpetaDatos, "Configuracion.ini");
            archivoLog = Path.Combine(carpetaLogs, "lanzador.log");

            Directory.CreateDirectory(carpetaLogs);
            Log("==== Iniciando " + NombreSistema + " " + VersionDelPaquete() + " desde " + carpetaPrograma);

            SystemEvents.SessionEnding += delegate { Detener(); };

            ventanaInicio = new VentanaInicio();
            ventanaInicio.Show();
            contextoInterfaz = SynchronizationContext.Current;
            idHiloInterfaz = Thread.CurrentThread.ManagedThreadId;

            Thread hilo = new Thread(Iniciar);
            hilo.IsBackground = true;
            hilo.Start();
        }

        private static string CarpetaDatosUsuario()
        {
            return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), NombreSistema);
        }

        public static void AbrirNavegadorDeInstanciaActiva()
        {
            string archivoPuerto = Path.Combine(CarpetaDatosUsuario(), "puerto-activo.txt");
            int puerto = PuertoSistemaPorDefecto;
            try
            {
                if (File.Exists(archivoPuerto))
                {
                    int.TryParse(File.ReadAllText(archivoPuerto).Trim(), out puerto);
                }
            }
            catch (IOException)
            {
            }
            AbrirUrl("http://127.0.0.1:" + puerto + "/");
        }

        // ------------------------------------------------------------------
        // Arranque
        // ------------------------------------------------------------------

        private void Iniciar()
        {
            try
            {
                VerificarArchivosDelPaquete();

                Estado("Leyendo la configuración...");
                PrepararConfiguracion();

                CerrarProcesosHuerfanos();
                VerificarPuertoLibre(puertoMysql, "PUERTO_MYSQL");
                VerificarPuertoLibre(puertoSistema, "PUERTO_SISTEMA");

                PrepararStorage();
                IniciarMysql();
                PrepararBaseDeDatos();
                IniciarPhp();

                File.WriteAllText(Path.Combine(carpetaDatos, "puerto-activo.txt"), puertoSistema.ToString());
                Log("Sistema listo en " + UrlSistema());

                EnHiloDeInterfaz(delegate
                {
                    ventanaInicio.Close();
                    CrearIconoBandeja();
                    AbrirUrl(UrlSistema());
                });
            }
            catch (Exception ex)
            {
                Log("ERROR: " + ex);
                string mensaje = ex is ErrorDeInicio ? ex.Message : "Ocurrió un error inesperado: " + ex.Message;
                EnHiloDeInterfaz(delegate
                {
                    ventanaInicio.Hide();
                    MessageBox.Show(
                        mensaje + "\n\nDetalles en:\n" + archivoLog,
                        NombreSistema, MessageBoxButtons.OK, MessageBoxIcon.Error);
                });
                Detener();
                Finalizar();
            }
        }

        private void VerificarArchivosDelPaquete()
        {
            string[] requeridos =
            {
                Path.Combine(carpetaPhp, "php.exe"),
                Path.Combine(carpetaMysql, "bin\\mysqld.exe"),
                Path.Combine(carpetaApp, "artisan"),
                Path.Combine(carpetaPrograma, "Configuracion-inicial.ini")
            };
            foreach (string archivo in requeridos)
            {
                if (!File.Exists(archivo))
                {
                    throw new ErrorDeInicio(
                        "Falta el archivo:\n" + archivo +
                        "\n\nDescomprima el paquete completo (no lo ejecute desde dentro del .zip).");
                }
            }
        }

        private void PrepararConfiguracion()
        {
            if (!File.Exists(archivoConfiguracion))
            {
                File.Copy(Path.Combine(carpetaPrograma, "Configuracion-inicial.ini"), archivoConfiguracion);
                Log("Configuración creada en " + archivoConfiguracion);
            }

            textoConfiguracion = File.ReadAllText(archivoConfiguracion, Encoding.UTF8);
            configuracion = LeerIni(archivoConfiguracion);
            puertoSistema = LeerPuerto("PUERTO_SISTEMA", PuertoSistemaPorDefecto);
            puertoMysql = LeerPuerto("PUERTO_MYSQL", PuertoMysqlPorDefecto);

            if (puertoMysql == PuertoMysqlDesarrollo)
            {
                throw new ErrorDeInicio(
                    "PUERTO_MYSQL=" + PuertoMysqlDesarrollo + " no está permitido: es el puerto del MySQL de " +
                    "Laragon/XAMPP y el sistema podría usar una base de datos que no es la suya.\n" +
                    "Use otro puerto (por defecto " + PuertoMysqlPorDefecto + ").\n\nConfiguración: " + archivoConfiguracion);
            }

            string nombreBase = Valor("DB_DATABASE");
            if (!Regex.IsMatch(nombreBase, "^[A-Za-z0-9_]{1,64}$"))
            {
                throw new ErrorDeInicio(
                    "El valor DB_DATABASE de la configuración no es válido: \"" + nombreBase + "\".\n" +
                    "Use solo letras, números y guion bajo.\n\nConfiguración: " + archivoConfiguracion);
            }

            foreach (string reservada in BasesReservadas)
            {
                if (string.Equals(nombreBase, reservada, StringComparison.OrdinalIgnoreCase))
                {
                    throw new ErrorDeInicio(
                        "El nombre de base de datos \"" + nombreBase + "\" está reservado.\n" +
                        "Use otro nombre en DB_DATABASE (por defecto ReservasPaquete).\n\nConfiguración: " + archivoConfiguracion);
                }
            }
        }

        private int LeerPuerto(string clave, int porDefecto)
        {
            string texto = Valor(clave);
            if (texto.Length == 0)
            {
                return porDefecto;
            }
            int puerto;
            if (!int.TryParse(texto, out puerto) || puerto < 1024 || puerto > 65535)
            {
                throw new ErrorDeInicio("El valor " + clave + "=" + texto + " no es un puerto válido (1024-65535).");
            }
            return puerto;
        }

        private void VerificarPuertoLibre(int puerto, string clave)
        {
            TcpListener prueba = null;
            try
            {
                prueba = new TcpListener(IPAddress.Loopback, puerto);
                prueba.Start();
            }
            catch (SocketException)
            {
                throw new ErrorDeInicio(
                    "El puerto " + puerto + " ya está en uso por otro programa.\n" +
                    "Cambie " + clave + " en la configuración:\n" + archivoConfiguracion);
            }
            finally
            {
                if (prueba != null)
                {
                    prueba.Stop();
                }
            }
        }

        /// <summary>
        /// Si una sesión anterior terminó de forma abrupta pueden quedar mysqld/php de este paquete corriendo.
        /// </summary>
        private void CerrarProcesosHuerfanos()
        {
            CerrarProcesosDe("mysqld", Path.Combine(carpetaMysql, "bin\\mysqld.exe"));
            CerrarProcesosDe("php", Path.Combine(carpetaPhp, "php.exe"));
        }

        private void CerrarProcesosDe(string nombre, string rutaEsperada)
        {
            foreach (Process proceso in Process.GetProcessesByName(nombre))
            {
                try
                {
                    if (string.Equals(proceso.MainModule.FileName, rutaEsperada, StringComparison.OrdinalIgnoreCase))
                    {
                        Log("Cerrando proceso anterior " + nombre + " (PID " + proceso.Id + ")");
                        proceso.Kill();
                        proceso.WaitForExit(15000);
                    }
                }
                catch (Exception ex)
                {
                    Log("No se pudo revisar el proceso " + proceso.Id + ": " + ex.Message);
                }
            }
        }

        private void PrepararStorage()
        {
            string storage = CarpetaStorage();
            string[] subcarpetas =
            {
                "app\\public",
                "app\\temp",
                "framework\\cache\\data",
                "framework\\sessions",
                "framework\\views",
                "logs"
            };
            foreach (string sub in subcarpetas)
            {
                Directory.CreateDirectory(Path.Combine(storage, sub));
            }

            // public\storage -> storage\app\public (unión de directorio; no requiere permisos de administrador)
            string enlace = Path.Combine(carpetaApp, "public\\storage");
            string destino = Path.Combine(storage, "app\\public");
            if (Directory.Exists(enlace))
            {
                if ((File.GetAttributes(enlace) & FileAttributes.ReparsePoint) == 0)
                {
                    return;
                }
                Directory.Delete(enlace, false);
            }
            Ejecutar("cmd.exe", "/c mklink /J \"" + enlace + "\" \"" + destino + "\"", carpetaApp, null, 30);
        }

        private string CarpetaStorage()
        {
            return Path.Combine(carpetaDatos, "storage");
        }

        // ------------------------------------------------------------------
        // MySQL
        // ------------------------------------------------------------------

        private void IniciarMysql()
        {
            string datos = Path.Combine(carpetaDatos, "mysql-data");
            string archivoIni = Path.Combine(carpetaDatos, "my.ini");
            string mysqld = Path.Combine(carpetaMysql, "bin\\mysqld.exe");

            File.WriteAllText(archivoIni, string.Join("\r\n", new[]
            {
                "[mysqld]",
                "basedir=\"" + RutaMysql(carpetaMysql) + "\"",
                "datadir=\"" + RutaMysql(datos) + "\"",
                "port=" + puertoMysql,
                "bind-address=127.0.0.1",
                "mysqlx=OFF",
                "character-set-server=utf8mb4",
                "collation-server=utf8mb4_unicode_ci",
                "max_allowed_packet=256M",
                "log-error=\"" + RutaMysql(Path.Combine(carpetaLogs, "mysql.err")) + "\"",
                ""
            }), new UTF8Encoding(false));

            if (!Directory.Exists(Path.Combine(datos, "mysql")))
            {
                Estado("Preparando la base de datos por primera vez (puede tardar un minuto)...");
                if (Directory.Exists(datos) && Directory.GetFileSystemEntries(datos).Length > 0)
                {
                    throw new ErrorDeInicio("La carpeta de datos de MySQL está incompleta:\n" + datos);
                }
                int codigo = Ejecutar(mysqld, "--defaults-file=\"" + archivoIni + "\" --initialize-insecure", carpetaMysql, null, 600);
                if (codigo != 0)
                {
                    throw new ErrorDeInicio("No se pudo inicializar MySQL. Revise " + Path.Combine(carpetaLogs, "mysql.err"));
                }
            }

            Estado("Iniciando la base de datos...");
            procesoMysql = IniciarProceso(mysqld, "--defaults-file=\"" + archivoIni + "\"", carpetaMysql, null);
            EsperarPuerto(puertoMysql, procesoMysql, 120, "MySQL", Path.Combine(carpetaLogs, "mysql.err"));
        }

        private static string RutaMysql(string ruta)
        {
            return ruta.Replace('\\', '/');
        }

        private void PrepararBaseDeDatos()
        {
            string nombreBase = Valor("DB_DATABASE");
            string mysql = Path.Combine(carpetaMysql, "bin\\mysql.exe");
            string sql = "CREATE DATABASE IF NOT EXISTS `" + nombreBase + "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

            int codigo = Ejecutar(mysql,
                "--host=127.0.0.1 --port=" + puertoMysql + " --user=root --execute=\"" + sql + "\"",
                carpetaMysql, null, 60);
            if (codigo != 0)
            {
                throw new ErrorDeInicio("No se pudo crear la base de datos " + nombreBase + ".");
            }

            Estado("Actualizando la estructura de la base de datos...");
            codigo = Ejecutar(Path.Combine(carpetaPhp, "php.exe"),
                ArgumentosPhp() + " artisan migrate --force --no-interaction",
                carpetaApp, VariablesLaravel(), 900);
            if (codigo != 0)
            {
                throw new ErrorDeInicio("No se pudo actualizar la estructura de la base de datos (migraciones).");
            }

            string marcaDatosIniciales = Path.Combine(carpetaDatos, "datos-iniciales-" + nombreBase.ToLowerInvariant() + ".ok");
            if (!File.Exists(marcaDatosIniciales))
            {
                Estado("Cargando los datos iniciales...");
                codigo = Ejecutar(Path.Combine(carpetaPhp, "php.exe"),
                    ArgumentosPhp() + " artisan db:seed --force --no-interaction",
                    carpetaApp, VariablesLaravel(), 900);
                if (codigo != 0)
                {
                    throw new ErrorDeInicio("No se pudieron cargar los datos iniciales (seeders).");
                }
                File.WriteAllText(marcaDatosIniciales, DateTime.Now.ToString("s"));
            }

            Match version = Regex.Match(VersionDelPaquete(), "^(\\d+)\\.(\\d+)\\.(\\d+)\\.(\\d+)$");
            if (version.Success)
            {
                codigo = Ejecutar(Path.Combine(carpetaPhp, "php.exe"),
                    ArgumentosPhp() + " artisan system:version " +
                    version.Groups[1].Value + " " + version.Groups[2].Value + " " +
                    version.Groups[3].Value + " " + version.Groups[4].Value +
                    " --if-different --description=\"Paquete " + version.Value + "\" --no-interaction",
                    carpetaApp, VariablesLaravel(), 120);
                if (codigo != 0)
                {
                    throw new ErrorDeInicio("No se pudo registrar la versión " + version.Value + " del sistema.");
                }
            }
        }

        private string VersionDelPaquete()
        {
            string archivo = Path.Combine(carpetaApp, "VERSION");
            return File.Exists(archivo) ? File.ReadAllText(archivo).Trim() : "";
        }

        // ------------------------------------------------------------------
        // PHP / Laravel
        // ------------------------------------------------------------------

        private void IniciarPhp()
        {
            Estado("Iniciando " + NombreSistema + "...");
            string enrutador = Path.Combine(carpetaApp, "vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\resources\\server.php");
            procesoPhp = IniciarProceso(
                Path.Combine(carpetaPhp, "php.exe"),
                ArgumentosPhp() + " -S 127.0.0.1:" + puertoSistema + " \"" + enrutador + "\"",
                Path.Combine(carpetaApp, "public"),
                VariablesLaravel());
            EsperarPuerto(puertoSistema, procesoPhp, 60, NombreSistema, null);
        }

        private string ArgumentosPhp()
        {
            string ext = Path.Combine(carpetaPhp, "ext");
            string certificados = Path.Combine(carpetaPhp, "extras\\ssl\\cacert.pem");
            // Sin caché en disco, el OPcache de PHP 8.5+ en Windows aborta "php -S" (Opcode handlers are unusable due to ASLR)
            string cacheOpcache = Path.Combine(carpetaDatos, "opcache");
            Directory.CreateDirectory(cacheOpcache);
            return "-c \"" + Path.Combine(carpetaPhp, "php.ini") + "\"" +
                   " -d extension_dir=\"" + ext + "\"" +
                   " -d curl.cainfo=\"" + certificados + "\"" +
                   " -d openssl.cafile=\"" + certificados + "\"" +
                   " -d opcache.file_cache=\"" + cacheOpcache + "\"";
        }

        /// <summary>
        /// Variables de entorno para Laravel. Tienen prioridad sobre app\.env.
        /// La conexión a MySQL se fija siempre al MySQL del paquete, aunque el .ini diga otra cosa.
        /// </summary>
        private Dictionary<string, string> VariablesLaravel()
        {
            Dictionary<string, string> variables = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            foreach (KeyValuePair<string, string> par in configuracion)
            {
                if (Array.IndexOf(ClavesDelLanzador, par.Key.ToUpperInvariant()) < 0)
                {
                    variables[par.Key] = par.Value;
                }
            }

            string url = UrlSistema().TrimEnd('/');
            variables["APP_URL"] = url;
            variables["FRONTEND_URL"] = url;
            variables["CORS_ALLOWED_ORIGINS"] = url + ",http://localhost:" + puertoSistema;
            // El frontend usa token Bearer: si su origen fuera "stateful", statefulApi() exigiría CSRF (error 419)
            variables["SANCTUM_STATEFUL_DOMAINS"] = "none.invalid";
            variables["DB_CONNECTION"] = "mysql";
            variables["DB_HOST"] = "127.0.0.1";
            variables["DB_PORT"] = puertoMysql.ToString();
            variables["DB_DATABASE"] = Valor("DB_DATABASE");
            variables["DB_USERNAME"] = "root";
            variables["DB_PASSWORD"] = "";
            variables["MYSQLDUMP_PATH"] = Path.Combine(carpetaMysql, "bin\\mysqldump.exe");
            variables["MYSQL_PATH"] = Path.Combine(carpetaMysql, "bin\\mysql.exe");
            variables["LARAVEL_STORAGE_PATH"] = CarpetaStorage();
            return variables;
        }

        private string UrlSistema()
        {
            return "http://127.0.0.1:" + puertoSistema + "/";
        }

        // ------------------------------------------------------------------
        // Procesos
        // ------------------------------------------------------------------

        private Process IniciarProceso(string exe, string argumentos, string carpeta, Dictionary<string, string> variables)
        {
            Log("> " + Path.GetFileName(exe) + " " + argumentos);
            ProcessStartInfo info = CrearInicio(exe, argumentos, carpeta, variables);
            return Process.Start(info);
        }

        /// <summary>Ejecuta un proceso, espera a que termine y guarda su salida en el log.</summary>
        private int Ejecutar(string exe, string argumentos, string carpeta, Dictionary<string, string> variables, int segundosMaximos)
        {
            Log("> " + Path.GetFileName(exe) + " " + argumentos);
            ProcessStartInfo info = CrearInicio(exe, argumentos, carpeta, variables);
            info.RedirectStandardOutput = true;
            info.RedirectStandardError = true;
            info.StandardOutputEncoding = Encoding.UTF8;
            info.StandardErrorEncoding = Encoding.UTF8;

            using (Process proceso = new Process())
            {
                proceso.StartInfo = info;
                proceso.OutputDataReceived += delegate(object s, DataReceivedEventArgs e) { if (e.Data != null) Log("  " + e.Data); };
                proceso.ErrorDataReceived += delegate(object s, DataReceivedEventArgs e) { if (e.Data != null) Log("  ! " + e.Data); };
                proceso.Start();
                proceso.BeginOutputReadLine();
                proceso.BeginErrorReadLine();

                if (!proceso.WaitForExit(segundosMaximos * 1000))
                {
                    try { proceso.Kill(); } catch (InvalidOperationException) { }
                    throw new ErrorDeInicio("La operación tardó demasiado: " + Path.GetFileName(exe) + " " + argumentos);
                }
                proceso.WaitForExit();
                Log("  (código de salida " + proceso.ExitCode + ")");
                return proceso.ExitCode;
            }
        }

        private static ProcessStartInfo CrearInicio(string exe, string argumentos, string carpeta, Dictionary<string, string> variables)
        {
            ProcessStartInfo info = new ProcessStartInfo(exe, argumentos);
            info.WorkingDirectory = carpeta;
            info.UseShellExecute = false;
            info.CreateNoWindow = true;
            if (variables != null)
            {
                foreach (KeyValuePair<string, string> par in variables)
                {
                    info.EnvironmentVariables[par.Key] = par.Value;
                }
            }
            return info;
        }

        private static void EsperarPuerto(int puerto, Process proceso, int segundosMaximos, string nombre, string logDetalle)
        {
            DateTime limite = DateTime.Now.AddSeconds(segundosMaximos);
            while (DateTime.Now < limite)
            {
                if (proceso.HasExited)
                {
                    throw new ErrorDeInicio(nombre + " se cerró al iniciar (código " + proceso.ExitCode + ")." +
                                            (logDetalle != null ? "\nRevise " + logDetalle : ""));
                }
                try
                {
                    using (TcpClient cliente = new TcpClient())
                    {
                        cliente.Connect(IPAddress.Loopback, puerto);
                        return;
                    }
                }
                catch (SocketException)
                {
                    Thread.Sleep(500);
                }
            }
            throw new ErrorDeInicio(nombre + " no respondió en el puerto " + puerto + " después de " + segundosMaximos + " segundos.");
        }

        // ------------------------------------------------------------------
        // Bandeja del sistema y cierre
        // ------------------------------------------------------------------

        private void CrearIconoBandeja()
        {
            ContextMenuStrip menu = new ContextMenuStrip();
            ToolStripItem abrir = menu.Items.Add("Abrir " + NombreSistema, null, delegate { AbrirUrl(UrlSistema()); });
            abrir.Font = new Font(abrir.Font, FontStyle.Bold);
            menu.Items.Add("Editar configuración", null, delegate { AbrirConfiguracion(); });
            menu.Items.Add("Abrir carpeta de registros", null, delegate { Process.Start("explorer.exe", "\"" + carpetaLogs + "\""); });
            menu.Items.Add(new ToolStripSeparator());
            menu.Items.Add("Reiniciar", null, delegate { Reiniciar(); });
            menu.Items.Add("Salir", null, delegate { Salir(); });

            iconoBandeja = new NotifyIcon();
            iconoBandeja.Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
            iconoBandeja.Text = (NombreSistema + " " + VersionDelPaquete()).Trim() + " (" + UrlSistema() + ")";
            iconoBandeja.ContextMenuStrip = menu;
            iconoBandeja.DoubleClick += delegate { AbrirUrl(UrlSistema()); };
            iconoBandeja.Visible = true;
            iconoBandeja.ShowBalloonTip(5000, NombreSistema,
                "El sistema está funcionando. Para cerrarlo use este ícono junto al reloj.", ToolTipIcon.Info);

            VigilarConfiguracion();
        }

        /// <summary>
        /// La configuración solo se lee al arrancar: si el usuario la guarda con cambios, se reinicia solo.
        /// </summary>
        private void VigilarConfiguracion()
        {
            // El Bloc de notas puede generar varios eventos por guardado: se espera a que se calme
            esperaCambiosConfiguracion = new System.Windows.Forms.Timer();
            esperaCambiosConfiguracion.Interval = 1500;
            esperaCambiosConfiguracion.Tick += delegate
            {
                esperaCambiosConfiguracion.Stop();
                RevisarCambiosConfiguracion();
            };

            vigilanteConfiguracion = new FileSystemWatcher(carpetaDatos, Path.GetFileName(archivoConfiguracion));
            vigilanteConfiguracion.NotifyFilter = NotifyFilters.LastWrite | NotifyFilters.Size | NotifyFilters.FileName;
            FileSystemEventHandler alCambiar = delegate
            {
                EnHiloDeInterfaz(delegate
                {
                    esperaCambiosConfiguracion.Stop();
                    esperaCambiosConfiguracion.Start();
                });
            };
            vigilanteConfiguracion.Changed += alCambiar;
            vigilanteConfiguracion.Created += alCambiar;
            vigilanteConfiguracion.Renamed += delegate(object s, RenamedEventArgs e) { alCambiar(s, e); };
            vigilanteConfiguracion.EnableRaisingEvents = true;
        }

        private void RevisarCambiosConfiguracion()
        {
            string texto;
            try
            {
                texto = File.ReadAllText(archivoConfiguracion, Encoding.UTF8);
            }
            catch (IOException)
            {
                esperaCambiosConfiguracion.Start();
                return;
            }

            if (texto != textoConfiguracion)
            {
                Log("La configuración cambió; reiniciando para aplicarla.");
                Reiniciar();
            }
        }

        private void Reiniciar()
        {
            if (vigilanteConfiguracion != null)
            {
                vigilanteConfiguracion.EnableRaisingEvents = false;
            }
            if (iconoBandeja != null)
            {
                iconoBandeja.Visible = false;
            }

            VentanaInicio aviso = new VentanaInicio();
            aviso.MostrarEstado("Reiniciando para aplicar la configuración...");
            aviso.Show();
            aviso.Refresh();

            Detener();
            Process.Start(Application.ExecutablePath, Program.ArgumentoReinicio);
            aviso.Close();
            Finalizar();
        }

        private void AbrirConfiguracion()
        {
            Process.Start("notepad.exe", "\"" + archivoConfiguracion + "\"");
        }

        private void Salir()
        {
            if (iconoBandeja != null)
            {
                iconoBandeja.Visible = false;
            }
            Detener();
            Finalizar();
        }

        private void Detener()
        {
            lock (this)
            {
                if (cerrando)
                {
                    return;
                }
                cerrando = true;
            }

            Log("Deteniendo " + NombreSistema + "...");
            if (procesoPhp != null && !procesoPhp.HasExited)
            {
                try { procesoPhp.Kill(); } catch (Exception ex) { Log("No se pudo cerrar PHP: " + ex.Message); }
            }

            if (procesoMysql != null && !procesoMysql.HasExited)
            {
                try
                {
                    Ejecutar(Path.Combine(carpetaMysql, "bin\\mysqladmin.exe"),
                        "--host=127.0.0.1 --port=" + puertoMysql + " --user=root shutdown",
                        carpetaMysql, null, 30);
                    if (!procesoMysql.WaitForExit(30000))
                    {
                        procesoMysql.Kill();
                    }
                }
                catch (Exception ex)
                {
                    Log("No se pudo cerrar MySQL de forma ordenada: " + ex.Message);
                    try { procesoMysql.Kill(); } catch (Exception) { }
                }
            }

            try { File.Delete(Path.Combine(carpetaDatos, "puerto-activo.txt")); } catch (IOException) { }
            Log("Detenido.");
        }

        private void Finalizar()
        {
            EnHiloDeInterfaz(delegate
            {
                if (iconoBandeja != null)
                {
                    iconoBandeja.Dispose();
                }
                ExitThread();
            });
        }

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        private static Dictionary<string, string> LeerIni(string archivo)
        {
            Dictionary<string, string> valores = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            foreach (string lineaOriginal in File.ReadAllLines(archivo, Encoding.UTF8))
            {
                string linea = lineaOriginal.Trim();
                if (linea.Length == 0 || linea.StartsWith("#") || linea.StartsWith(";") || linea.StartsWith("["))
                {
                    continue;
                }
                int igual = linea.IndexOf('=');
                if (igual <= 0)
                {
                    continue;
                }
                string clave = linea.Substring(0, igual).Trim();
                string valor = linea.Substring(igual + 1).Trim();
                if (valor.Length >= 2 && valor[0] == '"' && valor[valor.Length - 1] == '"')
                {
                    valor = valor.Substring(1, valor.Length - 2);
                }
                valores[clave] = valor;
            }
            return valores;
        }

        private string Valor(string clave)
        {
            string valor;
            return configuracion.TryGetValue(clave, out valor) ? valor.Trim() : "";
        }

        private static void AbrirUrl(string url)
        {
            Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
        }

        private void Estado(string texto)
        {
            Log(texto);
            EnHiloDeInterfaz(delegate { ventanaInicio.MostrarEstado(texto); });
        }

        private void EnHiloDeInterfaz(MethodInvoker accion)
        {
            if (Thread.CurrentThread.ManagedThreadId == idHiloInterfaz)
            {
                accion();
            }
            else
            {
                contextoInterfaz.Send(delegate { accion(); }, null);
            }
        }

        private readonly object bloqueoLog = new object();

        private void Log(string texto)
        {
            lock (bloqueoLog)
            {
                try
                {
                    File.AppendAllText(archivoLog, DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "  " + texto + "\r\n", Encoding.UTF8);
                }
                catch (IOException)
                {
                }
            }
        }
    }

    internal sealed class ErrorDeInicio : Exception
    {
        public ErrorDeInicio(string mensaje) : base(mensaje)
        {
        }
    }

    internal sealed class VentanaInicio : Form
    {
        private readonly Label etiquetaEstado;

        public VentanaInicio()
        {
            Text = "Reservas";
            FormBorderStyle = FormBorderStyle.FixedDialog;
            ControlBox = false;
            StartPosition = FormStartPosition.CenterScreen;
            ClientSize = new Size(420, 120);
            ShowInTaskbar = true;
            try
            {
                Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
            }
            catch (ArgumentException)
            {
            }

            Label titulo = new Label();
            titulo.Text = "Iniciando Reservas";
            titulo.Font = new Font(Font.FontFamily, 12, FontStyle.Bold);
            titulo.AutoSize = true;
            titulo.Location = new Point(20, 15);

            etiquetaEstado = new Label();
            etiquetaEstado.Text = "Preparando...";
            etiquetaEstado.AutoSize = false;
            etiquetaEstado.Size = new Size(380, 20);
            etiquetaEstado.Location = new Point(20, 48);

            ProgressBar barra = new ProgressBar();
            barra.Style = ProgressBarStyle.Marquee;
            barra.MarqueeAnimationSpeed = 30;
            barra.Size = new Size(380, 18);
            barra.Location = new Point(20, 80);

            Controls.Add(titulo);
            Controls.Add(etiquetaEstado);
            Controls.Add(barra);
        }

        public void MostrarEstado(string texto)
        {
            etiquetaEstado.Text = texto;
        }
    }
}
