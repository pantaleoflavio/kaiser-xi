# API Guide

## Purpose

This document describes the conventions used by the FantaMeister REST API.

It focuses on consistency rather than documenting every endpoint individually.

---

# General principles

The backend exposes a versioned REST API.

Current version:

```text
/api/v1
```

Future API versions will coexist with previous versions whenever breaking changes are introduced.

---

# Authentication

Authentication is handled through Laravel Sanctum.

Protected endpoints require a valid authenticated user.

Public endpoints remain accessible without authentication.

---

# Authorization

Authorization is implemented through Laravel Policies.

Access depends on:

* global platform roles;
* league-specific roles;
* ownership of domain resources.

Every protected endpoint performs authorization before executing business logic.

---

# Request validation

All incoming requests are validated using dedicated Form Request classes.

Controllers never perform inline validation.

Validation errors return the standard Laravel validation response.

---

# Response format

Collections are returned through Laravel API Resources.

Example:

```json
{
    "data": [
        {
            "id": 1,
            "name": "Example"
        }
    ]
}
```

Single resources follow the same convention.

```json
{
    "data": {
        "id": 1,
        "name": "Example"
    }
}
```

---

# HTTP status codes

The API follows standard REST conventions.

| Status | Meaning                            |
| ------ | ---------------------------------- |
| 200    | Successful request                 |
| 201    | Resource created                   |
| 204    | Successful request without content |
| 400    | Invalid request                    |
| 401    | Authentication required            |
| 403    | Forbidden                          |
| 404    | Resource not found                 |
| 409    | Business rule conflict             |
| 422    | Validation failed                  |
| 500    | Unexpected server error            |

---

# Resource naming

Resources use plural nouns.

Examples:

```text
/leagues

/fantasy-teams

/members

/invitations
```

Nested resources express ownership.

Example:

```text
/leagues/{league}/fantasy-teams
```

---

# HTTP verbs

The API follows REST conventions.

| Method | Purpose                    |
| ------ | -------------------------- |
| GET    | Retrieve resources         |
| POST   | Create resources           |
| PATCH  | Partially update resources |
| DELETE | Remove resources           |

PUT is intentionally avoided.

---

# Resource identifiers

Internal resources are identified by numeric IDs.

Invitation acceptance uses public invitation codes instead of database identifiers.

---

# Versioning

Every endpoint is exposed under:

```text
/api/v1
```

Breaking API changes should result in a new version.

---

# Current endpoint groups

The API currently exposes endpoints for:

* health check;
* authentication;
* leagues;
* league memberships;
* league invitations;
* fantasy teams.

Additional modules will be introduced as the project evolves.

---

# Error handling

Business rule violations are represented by dedicated exceptions whenever appropriate.

Examples include:

* duplicate ownership;
* invalid league membership;
* authorization failures.

The API aims to provide meaningful HTTP status codes instead of generic server errors.

---

# Serialization

Laravel API Resources define the public representation of domain models.

Resources are responsible for:

* hiding internal attributes;
* exposing computed values;
* formatting nested relationships.

Models are never returned directly.

---

# Pagination

Endpoints returning large collections should use Laravel pagination.

Small collections may return complete datasets when appropriate.

---

# Route model binding

Laravel Route Model Binding is used whenever possible.

Nested resources use scoped bindings to ensure child resources belong to their parent resource.

This avoids manual relationship checks inside controllers.

---

# Future API modules

The API is designed to grow with the application.

Planned endpoint groups include:

* players;
* fantasy rosters;
* formations;
* transfers;
* auctions;
* matchdays;
* standings;
* statistics.

These modules will follow the same conventions described in this document.
