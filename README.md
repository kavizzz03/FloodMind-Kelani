# 🌊 FloodMind Kelani

> **Advanced Hydrological Telemetry & Early Warning Command Framework**
> Real-time river basin monitoring and disaster risk mitigation gateway engineered for the Kelani River Basin, Sri Lanka.

---

## 🛠️ System Architecture & Core Highlights

FloodMind Kelani is designed as a centralized data tracking hub built to address critical flooding threats along the Kelani River. It bridges live environmental telemetry with automated localized disaster management pipelines.

### ⚡ Technical Capabilities
* **Dynamic Basin Cross-Section Mapping:** Live visual bar charts evaluating volume heights across major hydrological tracking stations (*Nagalagam Street, Hanwella, Glencourse, Kithulgala, Holombuwa, and Deraniyagala*).
* **Intelligent Disaster Risk Automation:** An automated threshold analysis engine that instantly classifies alerts into **Minor Flood Boundaries**, **Major Flood Warnings**, or **Critical Evacuation Notices** alongside automated dispatch hotlines.
* **Granular Spatial Filtering:** Localized administrative filtering enabling cascading breakdowns from Districts to Pradeshiya/Nagara Sabhas down to Grama Niladhari (GN) divisions.
* **Premium Glassmorphic Interface:** A fully modernized dark-theme layout built with native CSS blur effects, responsive fluid layouts (Desktop + Mobile optimization), and progressive landing animation timelines.

---

## 🏗️ Technical Stack & Dependencies

* **Language Platform:** PHP 8.x+ Core Application Routing
* **Styling Framework:** Tailwind CSS CDN (Utility Architecture)
* **Data Visualization:** ChartJS Engine (HTML5 Canvas Rendering)
* **Geospatial & Climate APIs:** Open-Meteo Weather Forecasting Systems

---

## 📂 Architecture Structure

```text
├── index.php                 # Core Welcome Overlay & Command Control Gateway
├── dashboard.php             # Target Operational Telemetry Panel Routing
├── water_monitor.php         # Real-time Decoupled Sensor Monitoring Stream
├── analytics_data.php        # 24H Basin Analytical Aggregates API endpoint
├── get_locations.php         # Administrative Geolocation Grid Matrix Controller
└── README.md                 # Project Structural Documentation
