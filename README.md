# Tutor Booking API

A RESTful API for managing tutor availability and student bookings built with Laravel 12.

## ✨ Features

- 🔐 Role-based access control (Student & Tutor)
- 🎫 Authentication with Laravel Sanctum
- 📅 Availability scheduling
- 📚 Booking management with validation
- ⏰ 24-hour cancellation policy
- 🏗️ Clean architecture with DTOs and Services
- ✅ Comprehensive test suite

## 🛠️ Tech Stack

- **Framework:** Laravel 12
- **Language:** PHP 8.2+
- **Database:** PostgreSQL 15
- **Authentication:** Laravel Sanctum 4.2
- **Testing:** PHPUnit
- **Code Style:** Laravel Pint

## 🚀 Quick Start (Docker)

### 1. Clone and Start
```bash
git clone https://github.com/aayush-vyas/tutor-booking-backend.git
cd tutor-booking-backend
docker-compose up -d
```

### 2. Setup Application
```bash
# Generate app key
docker exec tutor-booking-api php artisan key:generate

# Run migrations
docker exec tutor-booking-api php artisan migrate

# (Optional) Seed test data
docker exec tutor-booking-api php artisan db:seed
```

### 3. Access API
✅ **API:** http://localhost:8080/api

### That's it! 🎉

## 💻 Manual Installation (Without Docker)

<details>
<summary>Click to expand manual setup steps</summary>

### Prerequisites
- PHP 8.2+
- Composer
- PostgreSQL 15+

### Steps

1. **Clone repository**
```bash
git clone https://github.com/aayush-vyas/tutor-booking-backend.git
cd tutor-booking-backend
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Setup database**
```env
# Update .env with your database credentials
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tutor_booking
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Start server**
```bash
php artisan serve
```

API available at: `http://localhost:8000/api`

</details>

## 📡 API Endpoints

### 🔐 Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login user |
| GET | `/api/auth/user` | Get current user |
| POST | `/api/auth/logout` | Logout user |

### 📅 Availability (Tutor Only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/availabilities` | List all availabilities |
| POST | `/api/availabilities` | Create availability slot |
| GET | `/api/availabilities/{id}` | Get specific availability |
| PUT | `/api/availabilities/{id}` | Update availability |
| DELETE | `/api/availabilities/{id}` | Delete availability |
| GET | `/api/tutors/{id}/schedule` | Get tutor schedule |

### 📚 Bookings
| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/api/bookings` | List bookings | Both |
| POST | `/api/bookings` | Create booking | Student |
| GET | `/api/bookings/{id}` | Get booking details | Both |
| POST | `/api/bookings/{id}/confirm` | Confirm booking | Tutor |
| POST | `/api/bookings/{id}/complete` | Mark completed | Tutor |
| POST | `/api/bookings/{id}/cancel` | Cancel booking | Both |
| POST | `/api/bookings/{id}/reschedule` | Reschedule booking | Both |
| GET | `/api/tutors/{id}/available-slots` | Get available slots | Both |

## 🐳 Docker Commands

```bash
# View logs
docker-compose logs -f app

# Access container shell
docker exec -it tutor-booking-api bash

# Run artisan commands
docker exec tutor-booking-api php artisan [command]

# Run tests
docker exec tutor-booking-api php artisan test

# Format code
docker exec tutor-booking-api ./vendor/bin/pint

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# Rebuild containers
docker-compose up -d --build
```

## 📝 Usage Examples

### Register a new user
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Student",
    "email": "student@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "student"
  }'
```

### Login
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@example.com",
    "password": "password123"
  }'
```

### Create a booking (Student)
```bash
curl -X POST http://localhost:8080/api/bookings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tutor_id": 2,
    "start_time": "2025-12-01 10:00:00",
    "end_time": "2025-12-01 11:00:00",
    "notes": "Need help with calculus"
  }'
```

## 📋 Business Rules

- ✅ Bookings must be within tutor's available time slots
- ✅ No overlapping bookings allowed
- ✅ Cannot cancel within 24 hours of booking start time
- ✅ Students create bookings, tutors confirm/complete them
- ✅ Tutors manage their availability schedule
- ✅ All times are validated and checked for conflicts

## 🧪 Testing

### Run all tests
```bash
# With Docker
docker exec tutor-booking-api php artisan test

# Without Docker
php artisan test
```

### Run specific test suite
```bash
docker exec tutor-booking-api php artisan test --testsuite=Feature
docker exec tutor-booking-api php artisan test --testsuite=Unit
```

### Code formatting
```bash
# Check code style
docker exec tutor-booking-api ./vendor/bin/pint --test

# Fix code style
docker exec tutor-booking-api ./vendor/bin/pint
```

## 📚 Documentation

- 📖 [Full API Documentation](./API_DOCUMENTATION_WITH_TABLES.md)
- 🔧 [Postman Collection](./Tutor_Booking_API.postman_collection.json)
- 🐳 [Docker Setup Guide](./DOCKER_SETUP.md)
- ✅ [Test Suite Summary](./TEST_SUITE_SUMMARY.md)

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests and code formatter
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## 📄 License

This project is licensed under the MIT License.

## 👤 Author

**Aayush Vyas**
- GitHub: [@aayush-vyas](https://github.com/aayush-vyas)

---

Made with ❤️ using Laravel 12
