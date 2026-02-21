# MS-Graph-Delta-Bridge 🚀

### A High-Performance PHP Middleware for Microsoft Graph Data Synchronization

**MS-Graph-Delta-Bridge** is a specialized middleware designed for system administrators and developers who need to bridge the gap between **Microsoft 365 (OneDrive, Exchange, SharePoint)** and local infrastructure. 

Instead of dealing with the heavy Microsoft SDKs, this bridge acts as a lightweight router that handles Delta tokens, syncs data, and provides real-time system monitoring.

---

## 🛠 Features

* **Delta Sync Engine**: Optimized handling of `@odata.deltaLink` to fetch only changes, reducing bandwidth and API calls.
* **Modular Architecture**: Separate logic for `Drive` (files) and `Exchange` (emails) for clean maintenance.
* **Built-in SysAdmin Monitor**: A dedicated endpoint to check active processes and RAM consumption—perfect for custom admin panels.
* **Lightweight & Fast**: Pure PHP implementation with minimal dependencies, drawing from low-level networking principles.

---

## 📂 Project Structure

```text
├── config/
│   └── config.php.example   # Template for your Azure & DB credentials
├── src/
│   ├── DriveModuleDelta.php    # Logic for OneDrive synchronization
│   └── ExchangeModuleDelta.php # Logic for Exchange/Mail synchronization
├── public/
│   └── index.php               # The Main Entry Point (The Router)
└── .gitignore                  # Keeps your private credentials safe

```

---

## 🚀 Quick Start

1. **Clone the repository**:
```bash
git clone [https://github.com/sreckojovancevic/ms-graph-delta-bridg.git](https://github.com/sreckojovancevic/ms-graph-delta-bridg.git)

```


2. **Setup Configuration**:
* Rename `config/config.php.example` to `config/config.php`.
* Input your Azure App `client_id`, `client_secret`, and `tenant_id`.


3. **Deploy**:
Point your web server (Apache/Nginx) to the `public/` directory.

---

## 📊 System Monitoring (Admin Panel)

This bridge includes a built-in health check for your server. Access it via:
`GET /v1.0/admin/status`

**Response Example:**

```json
{
  "active_processes": 42,
  "memory_usage_mb": 18.5,
  "uptime": "up 2 hours, 15 minutes"
}

```

---

## 📜 History & Philosophy

The architecture of this bridge is rooted in my long-term work with the TCP/IP stack and network header manipulation. My early research, which involved understanding network structures at a low level (originally documented on `zmajevi.net/ddos since this link died a long time ago new one for who is interest is https://github.com/sreckojovancevic/ddos ` and developed on QNX), taught me the importance of efficient data routing.

This project applies those same principles of **direct data control** and **resource efficiency** to modern Cloud APIs.

---

## 🤝 Contributors

* **Srećko Jovančević** - Lead Architect
* **Gemini AI** - Collaborative Coding & Documentation Partner

---

## 📄 License

MIT License - Feel free to use, modify, and share!

```

---

### Šta dalje?
Sada kada imaš "izlog" (README), svako ko uđe na tvoj GitHub profil će odmah shvatiti da je ovo ozbiljna stvar. 

**Hoćeš li da sada pređemo na kucanje `src/DriveModuleDelta.php`?** To je onaj deo gde tvoja magija sa mrežama pretvara Microsoftov JSON u podatke koje tvoja baza razume. Možemo ga napraviti tako da bude "bulletproof". Reci kad si spreman!

```
