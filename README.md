# Game Collector

A modern, responsive web application for managing your video game collection. Track your games, monitor prices across different platforms (Steam, GOG, PlayStation Store, eBay), and automatically fetch metadata.

## 🚀 Features

-   **Library Management**: Add, edit, and organize your game collection.
-   **Automated Metadata**: Automatically fetch game details (cover art, release dates, metascores) using the **RAWG API** and **IGDB API**.
-   **Price Tracking**:
    -   Real-time price updates from **Steam**, **GOG**, **CheapShark**, and **PriceCharting**.
    -   **eBay Integration**: Automatically estimates market value for physical games using "Buy It Now" listings.
    -   **PlayStation Store** integration (via custom web scraping) for console game prices.
-   **Steam Import**: Import your entire Steam library automatically.
-   **Image Scanning**: Add games by scanning their cover art (powered by Google Cloud Vision).
-   **Responsive Design**: Fully optimized for both desktop and mobile devices.
-   **Statistics**: Visual breakdown of your collection's value, platform distribution, and completion status.
-   **Reviews**: Read and write game reviews (Community features).

## 🛠️ Tech Stack

-   **Backend**: [Laravel 11](https://laravel.com/)
-   **Frontend**: [Vue.js 3](https://vuejs.org/) with [Inertia.js](https://inertiajs.com/)
-   **Styling**: [Tailwind CSS](https://tailwindcss.com/)
-   **Database**: SQLite (Default) / MySQL
-   **Testing**: PHPUnit / Pest

## ⚙️ Installation

1.  **Clone the repository**
    ```bash
    git clone https://github.com/yourusername/game-collector.git
    cd game-collector
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Setup**
    Copy the `.env.example` file to `.env`:
    ```bash
    cp .env.example .env
    ```

    Update the following variables in your `.env` file:
    ```env
    DB_CONNECTION=sqlite
    # DB_DATABASE=/absolute/path/to/database.sqlite (if not using default)

    # API Keys
    RAWG_API_KEY=your_rawg_key
    
    # IGDB (Twitch Developer)
    IGDB_CLIENT_ID=your_client_id
    IGDB_CLIENT_SECRET=your_client_secret

    # eBay (Developers Program)
    EBAY_CLIENT_ID=your_ebay_client_id
    EBAY_CLIENT_SECRET=your_ebay_client_secret

    # Other Services
    GOOGLE_APPLICATION_CREDENTIALS=path/to/google-vision-credentials.json
    PRICECHARTING_KEY=your_pricecharting_key
    ```

4.  **Generate App Key**
    ```bash
    php artisan key:generate
    ```

5.  **Database Setup**
    Create the SQLite database file (if it doesn't exist) and run migrations:
    ```bash
    touch database/database.sqlite
    php artisan migrate --seed
    ```

6.  **Build Assets**
    ```bash
    npm run build
    ```

## 🏃‍♂️ Running the Application

1.  Start the Laravel development server:
    ```bash
    php artisan serve
    ```

2.  (Optional) Watch for frontend changes:
    ```bash
    npm run dev
    ```

3.  Access the app at `http://localhost:8000`.

## 🧪 Testing

Run the test suite to ensure everything is working correctly:

```bash
php artisan test
```

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
