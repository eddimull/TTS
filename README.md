# TTS Bandmate

Booking application to eliminate the need for an agent.

Can help streamline the booking and scheduling process by automated sending of proposals and contracts, collecting payment through invoices, sending out advances, band library aggregation, and calendar integration.

## Setup

**Note:** Currently, the dockerfile is only geared toward a development environment, and is not optimized for production. For a production setup,
follow a typical Laravel installation.

**Prerequisites:**

- Docker / Podman
- PandaDoc account

Clone the repository to your desired directory.

Make a copy of the `.env.example` file and rename it to `.env`, and edit details as needed. Make sure a mail sender address is filled out, or mail will not work, which will break several parts of the program. A PandaDoc API key is also needed to complete proposals.

Make a `sail-selfsigned` certificate in the `ssl` directory (you may need to create this directory). For example, with `mkcert` on Linux/Mac:

```
mkdir ssl
mkcert -key-file=ssl/sail-selfsigned.key -cert-file=ssl/sail-selfsigned.crt localhost 127.0.0.1 ::1
```

Run `docker-compose up -d` or `podman-compose --in-pod 1 up -d` to start the application.

In the `app` container, run `composer install` and `npm ci` to install dependencies. Run `php artisan key:generate` to generate a new application key, and `php artisan migrate` to
create the database tables. Run `php artisan db:seed` to seed the database with some needed data.

You may also run `php artisan db:seed --class=DevSetupSeeder` to seed the
database with some generated testing data, including an admin user `admin@example.com` with password `password`.

In the `app` container, run `npm run watch` to watch for changes to the frontend files and automatically recompile.

A bucket needs to be created in MinIO / S3 for storing files. The bucket name needs match what is set in the `.env` file.

To create a bucket on MinIO, log into the MinIO web interface on http://localhost:9001 with the admin credentials (default is `minioadmin:minioadmin`) and create the bucket.

There are certain cron jobs that need to be setup to run. These can be run on the `app` container with `php artisan schedule:work`.

The app can be accessed at https://localhost:8170.

## Usage

A proposal draft is first created under the "Proposals" tab. Once the draft is created, more details can be filled in. If all required details (including a contact) are filled it, the draft can be finalized.

The finalized proposal can then be sent to the client for approval. Once the client has approved the proposal, the contract can be generated as a PDF and will be saved to S3 / Minio and can then be sent for signing in PandaDoc. Once all parties have signed, a cron job will move the contract to "Contact Signed".

Once the proposal is completed, an event will be created to match the proposal and added to the calendar for the band.

Events can also be created manually by clicking "Draft New Event" under the "Events" tab; they do not need a corresponding proposal.

Event payment can be recorded by selecting "Unpaid Contracts" under "Finances", and then clicking "Make Payment".

Charts can be uploaded to provide a centralized location for band members to obtain sheet music.

The dashboard will show a list of upcoming events with a link to an advance, as well as a calendar view of the upcoming events.
