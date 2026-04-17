# Profile Intelligence API

A RESTful backend service that enriches a given name using multiple third-party APIs, processes the data, and stores it for retrieval and management.

---

## 🚀 Live API

Base URL:

```
https://your-app-name.up.railway.app
```

---

## 📦 Features

* Integrates with 3 external APIs:

  * Genderize
  * Agify
  * Nationalize
* Aggregates and processes data
* Stores structured results in a database
* Implements idempotency (no duplicate profiles)
* Provides RESTful endpoints for CRUD operations
* Supports filtering on stored data

---

## 🛠️ Tech Stack

* PHP (Plain PHP)
* MySQL
* REST API Architecture

---

## 🔗 External APIs Used

* https://api.genderize.io
* https://api.agify.io
* https://api.nationalize.io

---

## 📊 Data Processing Rules

* `count` from Genderize → renamed to `sample_size`
* Age is classified into:

  * 0–12 → child
  * 13–19 → teenager
  * 20–59 → adult
  * 60+ → senior
* Country with highest probability is selected

---

## 📌 API Endpoints

### 1. Create Profile

**POST** `/api/profiles`

Request:

```json
{
  "name": "ella"
}
```

Response (201):

```json
{
  "status": "success",
  "data": {
    "id": "uuid",
    "name": "ella",
    "gender": "female",
    "gender_probability": 0.99,
    "sample_size": 1234,
    "age": 46,
    "age_group": "adult",
    "country_id": "US",
    "country_probability": 0.85,
    "created_at": "2026-04-17T12:00:00Z"
  }
}
```

Idempotency:

```json
{
  "status": "success",
  "message": "Profile already exists",
  "data": { ... }
}
```

---

### 2. Get Profile by ID

**GET** `/api/profiles/{id}`

Response (200):

```json
{
  "status": "success",
  "data": { ... }
}
```

---

### 3. List Profiles

**GET** `/api/profiles`

Optional query parameters:

* `gender`
* `country_id`
* `age_group`

Example:

```
/api/profiles?gender=male&country_id=NG
```

Response:

```json
{
  "status": "success",
  "count": 2,
  "data": [
    {
      "id": "id-1",
      "name": "emmanuel",
      "gender": "male",
      "age": 25,
      "age_group": "adult",
      "country_id": "NG"
    }
  ]
}
```

---

### 4. Delete Profile

**DELETE** `/api/profiles/{id}`

Response:

* `204 No Content`

---

## ⚠️ Error Handling

All errors follow this format:

```json
{
  "status": "error",
  "message": "Error message"
}
```

### Common Errors

* 400 → Missing name
* 404 → Profile not found
* 422 → Invalid input type
* 502 → External API failure

Example:

```json
{
  "status": "error",
  "message": "Genderize returned an invalid response"
}
```

---

## 🧪 Testing

Use tools like Postman to test endpoints.

---

## ⚙️ Setup Instructions

1. Clone the repository:

```
git clone https://github.com/YOUR_USERNAME/profile-intelligence-api.git
```

2. Configure database in `db.php`

3. Run your local server (e.g., XAMPP)

4. Access API:

```
http://localhost/profile-intelligence-api/api/profiles
```

---

## 🌐 Deployment

The API is deployed on Railway.

---


