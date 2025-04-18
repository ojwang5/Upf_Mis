import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

// Set document title
document.title = "MDD Manager";

// Add meta tags
const meta = document.createElement('meta');
meta.name = 'description';
meta.content = 'Personnel Management System for Brass Bands, Police Units, and Military Teams';
document.head.appendChild(meta);

// Add favicon
const link = document.createElement('link');
link.rel = 'icon';
link.href = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎵</text></svg>';
document.head.appendChild(link);

// Add Google Fonts
const fontLink = document.createElement('link');
fontLink.rel = 'stylesheet';
fontLink.href = 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Condensed:wght@400;700&display=swap';
document.head.appendChild(fontLink);

// Add Material Icons
const iconLink = document.createElement('link');
iconLink.rel = 'stylesheet';
iconLink.href = 'https://fonts.googleapis.com/icon?family=Material+Icons';
document.head.appendChild(iconLink);

createRoot(document.getElementById("root")!).render(<App />);
