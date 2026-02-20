A web-based platform for managing, monitoring, and evaluating faculty research production, utilizing AI for document verification and Scopus integration.

🛠 Prerequisites

Before setting up the project, ensure you have the following installed:

PHP 8.1 or higher

Composer

Node.js (v18+) & npm

MySQL (via XAMPP, Laragon, or direct install)

ImageMagick & Ghostscript (Required for PDF-to-Image thumbnail generation)

🚀 Installation Steps

1. Clone the Repository

git clone <your-repo-url>
cd ori_system


2. Backend Setup (Laravel)

cd laravel-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link


3. Frontend Setup (React)

cd ../react-frontend
npm install


⚙️ Environment Configuration (.env)

Open laravel-backend/.env and configure the following:

Database Settings

Crucial: Use 127.0.0.1 instead of localhost to avoid socket errors during backups.

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ori_system
DB_USERNAME=root
DB_PASSWORD=


AI Integration (Gemini API)

GEMINI_API_KEY=your_actual_gemini_api_key_here


🔧 Special Requirements & Fixes

1. Database Backup Fix (Windows)

To enable the Backup feature on Windows, you must tell Laravel where mysqldump.exe is located.

Open laravel-backend/config/database.php.

In the mysql array, ensure the dump path is set correctly:

'dump' => [
    'dump_binary_path' => 'C:\xampp\mysql\bin', // or path to Laragon bin
    'use_single_transaction',
    'timeout' => 300,
],


2. Imagick (PDF Thumbnail) Fix

If you encounter an error regarding Imagick, follow these steps:

Install Ghostscript: Download and install from the official site.

Install ImageMagick: Download the DLL version (e.g., ImageMagick-7.x-Q16-HDRI-x64-dll.exe).

PHP Extension: Add extension=imagick to your php.ini.

Environment Variables: Add the ImageMagick installation path to your Windows System PATH.

3. Date Format Error (MySQL 1292)

The system is pre-configured to handle ISO date strings from React. If modifying controllers, always use Carbon to format dates:

$start_date = Carbon::parse($request->start_date)->format('Y-m-d');


🏃 How to Run

Start the Backend

# In laravel-backend
php artisan serve


Start the Frontend

# In react-frontend
npm run dev


📁 Backup and Restore

Backup: Log in as Admin -> Settings -> Backup -> Create Backup. This creates a .zip file containing the SQL dump and all uploaded files (profile pictures/certificates).

Restore: Upload the .zip file in the Backup settings to restore the system state exactly.

👥 Roles

Admin: Manages users, sets academic years, archives research, and manages backups.

Faculty: Submits research monitoring forms and tracks points/evaluations.



Server Requirements for PDF Processing

The Research Monitoring System requires ImageMagick and Ghostscript to render PDF thumbnails.

For Ubuntu / Debian Servers (Recommended):
The server administrator simply needs to run the following commands via SSH:

Update packages:
sudo apt-get update

Install the required libraries and PHP extension:
sudo apt-get install ghostscript imagemagick php-imagick

Restart the web server (Apache or Nginx):
sudo systemctl restart apache2 OR sudo systemctl restart php8.x-fpm