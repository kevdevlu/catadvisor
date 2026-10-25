# CatAdvisor

A prototype of a tool for academic advisors: who to contact next and why, what was discussed last time, and a place to log new contact notes. Built with Laravel 13 (API) and React, on a dataset of 50 fictional students (`advisor_tracker_dataset/`). "Today" is fixed to Oct 5, 2026 to match the data.

> **Looking for how to try it?** This README is for developers. A separate one-page README covers how to use the hosted demo, who the app is for, what was assumed about them, and how AI tools were used. It's shared privately, together with the live URL and the demo password.

## What it does

- **First screen:** count cards that filter the list (due for outreach, high risk past target, waiting on a reply, never contacted), search and filters, and a sortable table. Status chips ("95 days past target", "No reply", "Follow-up needed") open the details in place.
- **Student page:** contact preference, next follow-up, waiting on a reply, reasons to reach out, an AI prep brief, the note form, and the note history. It opens in a side panel over the list, or as a full page.
- **The smart feature:** a rule-based "who to contact" order (the Status column), plus one Claude Haiku call each time a note is saved. That call writes the prep brief, sets the next follow-up and "waiting on a reply", flags a note that "may be sensitive" and asks whether to mark it private, and suggests contact-preference changes for the advisor to accept.
- **Admin:** a read-only account that sees every advisor's students, with an Advisors picker and a "Reset demo data" button.
- **Privacy:** each advisor sees only their own students. Private notes are readable only by their author and are never sent to the AI. The AI never gets a student's name, email, or ID. SSNs are removed before anything is stored. Every field is treated as a FERPA education record.

## Run it locally

Needs [DDEV](https://ddev.com) (Docker).

```bash
cp .env.example .env     # set DEMO_PASSWORD; add ANTHROPIC_API_KEY for the AI features
ddev start
ddev composer install
ddev artisan key:generate
ddev artisan migrate --seed
ddev npm install && ddev npm run build
```

Open https://catadvisor.ddev.site, pick an account (Lindqvist, Okafor, Reyes, or Admin), and sign in with `DEMO_PASSWORD`. Without an API key the app still works; the AI parts just stay off.

## Keep developing

- **Front end with live reload:** `ddev npm run dev` starts the Vite dev server (on port 5173), and https://catadvisor.ddev.site then picks up front-end changes as you save, without a rebuild. When it isn't running, the site uses the files from the last `ddev npm run build`. Laravel Cloud builds its own on each deploy.
- **Tests:** `ddev artisan test`. They use a separate `testing` database and a fake AI model, so they never touch the demo data or call the API.
- **Code style:** `ddev exec vendor/bin/pint` formats the PHP.
- **Reset the demo data:** `ddev artisan migrate:fresh --seed`, or sign in as Admin and click "Reset demo data".
- **Deploy:** the hosted demo runs on Laravel Cloud and deploys on every push to `main`. See [`docs/deploy.md`](docs/deploy.md).

## Future development

- Advisor settings: their own contact targets, and turning AI off.
- Student management (add, move, remove, archive), office-wide or per advisor.
- An admin dashboard: key stats per advisor, and issues such as possible duplicate student records.
- Edit history for notes.
- A mobile layout and dark mode.
- University sign-in (NetID SSO), in place of the shared demo password.
- Running the AI check fully in the background on the server, so it finishes even if the advisor closes the app right after saving a note.

## License

MIT, see [`LICENSE`](LICENSE). The icons come from Lucide; see [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).
