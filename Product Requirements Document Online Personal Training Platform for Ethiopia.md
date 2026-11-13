# Product Requirements Document: Online Personal Training Platform for Ethiopia

Draft v0.1 · November 13, 2026 · Owner: \[Your name\] · Status: For pilot validation

## 1. Summary

This product is a mobile-friendly platform that helps Ethiopian online personal trainers serve many clients at once, so they earn more while clients pay less. Trainers own their clients, set their own prices, and control retention. The platform gives them the tools to run their coaching business: a shareable trainer page, free programs that generate leads, paid coaching plans, reusable program templates, asynchronous check-ins, group programs, Telebirr and Chapa payments, and a simple earnings view.

The platform earns a 5% commission on payments processed through it. Trainers pay nothing upfront.

**One-line pitch:** Trainers coach more people with less effort; clients get affordable coaching; the platform earns when trainers earn.

## 2. Problem

**For trainers.** Online coaching in Ethiopia is just emerging. Most trainers who coach remotely work through Telegram, WhatsApp, and Instagram. They send workout plans by hand, track payments in their heads or in notebooks, chase clients for renewals, and struggle to respond to everyone. This caps how many clients a trainer can handle, which keeps income low and prices high.

**For clients.** One-on-one personal training is expensive and mostly limited to people near a good gym in Addis Ababa. Many people who want guided training cannot afford it.

**For both.** Global coaching tools exist but assume dollar cards, English-only content, fast internet, and Western pricing. They do not fit how Ethiopians pay, communicate, or connect.

**Why now.** Digital payments have reached scale: Telebirr reports roughly 59 million users, and Chapa serves more than 10,000 merchants. Smartphone access is growing but remains uneven, with estimates ranging from about 25% to 45% of adults depending on the source, concentrated in cities. The market is large enough to start in urban Ethiopia and is not yet occupied by a local coaching platform.

## 3. Vision and goals

**Vision.** Every Ethiopian who wants guided training can afford it, and every good trainer can build a real business from it.

### Goals for version 1

- Let a trainer sign up, publish a page, and start collecting leads in under 30 minutes.
- Let a trainer serve at least 3 times more paying clients than they could manage through chat apps alone.
- Let clients pay and receive coaching using only Telebirr or Chapa and a basic smartphone.
- Prove that people who try a free program will pay for coaching.
- Reach a state where trainer revenue through the platform covers the platform's own running costs.

### Non-goals for version 1

- A public marketplace or directory where clients browse and pick trainers.
- Live video classes.
- Meal plans, calorie tracking, and wearable integrations.
- Coaching outside fitness (nutrition-only, mental health, and so on).
- International expansion or dollar payments.
- A native app on app stores (version 1 is a mobile web app).

## 4. Target users

### Primary: Trainers

**Persona A: The growing trainer (priority target).** Based in Addis Ababa. Already coaches 5 to 20 online clients through Telegram or WhatsApp. Feels overwhelmed by admin, loses clients at renewal time, and wants more clients without more hours. Active on Instagram or TikTok. Comfortable with a smartphone.

**Persona B: The newer trainer.** Recently certified or self-taught. Has few clients, cannot fill a schedule at premium prices, and wants an affordable way to build an audience. Likely to use free programs heavily.

**Persona C: The gym-based trainer.** Trains in person but wants to add online clients for extra income. Needs simple tools and little training.

### Secondary: Clients

**Persona D: The urban beginner.** Age 20 to 40, lives in a city, owns a smartphone, wants to lose weight, build strength, or get fit at home. Price-sensitive. Uses Telebirr. Checks Telegram and social media daily.

**Persona E: The busy professional.** Wants structure and accountability, has limited time, and prefers flexible, asynchronous coaching over fixed session times.

## 5. Key assumptions to validate

1. Trainers want to coach more people at lower prices per client, and total income rises.
2. Enough trainers already have a social following or client base to bring their own clients to the platform.
3. Clients will pay for coaching after trying a free program.
4. Trainers will accept a 5% commission in exchange for the tools.
5. Clients will pay through the platform rather than sending money to the trainer directly.
6. Asynchronous coaching (voice notes, photos, short videos) works well enough on current data costs and connectivity.
7. Trainers can set prices that leave the platform's 5% worth earning after Telebirr and Chapa fees.

The pilot in section 14 exists to test these.

## 6. Product principles

1. **Trainer-owned.** Trainers own their clients, their pricing, and their relationships. The platform never contacts clients on its own behalf to sell other trainers.
2. **Scale without losing personal touch.** Automation handles repetition; the trainer's time goes where it matters.
3. **Light on data.** Every screen and video should work on weak connections.
4. **Local first.** Amharic and English, birr pricing, Telebirr and Chapa, Telegram reminders.
5. **Simple over complete.** Every feature must justify itself against the question: does it help trainers earn more or clients stay longer?
6. **Honest money.** Trainers always see exactly what they earned, what was deducted, and when they will be paid.

## 7. Core user journeys

### Journey A: Trainer onboarding

1. Trainer signs up with phone number and verifies by SMS code.
2. Enters name, photo, specialty, bio, and years of experience; optionally uploads a certification.
3. Creates a trainer page with a unique link.
4. Sets up payout details (Telebirr number or bank account).
5. Creates a first free program or paid plan using a template.
6. Shares the link on social media.

**Success:** Trainer has a live page and a shareable link in under 30 minutes.

### Journey B: Free program lead to paying client

1. A person sees the trainer's post and taps the free program link.
2. Lands on a simple page showing the program and a "Start free" button.
3. Enters name, phone number, and optional Telegram handle; answers a short health and goal questionnaire.
4. Receives day-one workout immediately and daily reminders.
5. Around the program's final days, receives the trainer's offer to continue with paid coaching.
6. Taps through, picks a plan, and pays through Telebirr or Chapa.
7. The trainer is notified, and the client moves from lead to paying client.

### Journey C: Weekly coaching loop

1. Client opens today's workout, watches demo clips, and logs the session.
2. Weekly, the client submits a check-in: weight, a photo, and a short note or voice message.
3. The trainer's "needs attention" list shows who has checked in, who has gone quiet, and who needs a reply.
4. Trainer replies with a voice note or text.
5. Client sees progress over time and stays motivated.

### Journey D: Renewal

1. Several days before the plan ends, the client receives a reminder with a one-tap renewal.
2. If the client does not renew, the trainer is alerted so they can reach out personally.
3. The trainer can offer a different plan or a pause.

## 8. Feature requirements

Priority key: **P0** = required for the first usable version. **P1** = needed soon after pilot feedback. **P2** = later.

### 8.1 Trainer account and profile page

- **T-1 (P0)** Sign up and log in with a phone number and SMS verification.
- **T-2 (P0)** Trainer profile: name, photo, short bio, specialty, languages, and optional certification upload.
- **T-3 (P0)** A public trainer page with a unique, shareable link that shows the trainer's profile, free programs, and paid plans.
- **T-4 (P0)** Page works well on a phone and loads quickly on weak connections.
- **T-5 (P1)** Trainer can add testimonials and before-and-after photos, with client consent.
- **T-6 (P1)** Trainer verification badge once an admin has reviewed their identity and credentials.

### 8.2 Free programs and lead capture

- **F-1 (P0)** Trainer can create a free program from a template or from scratch (for example a 7-day starter or a 14-day challenge).
- **F-2 (P0)** Each free program has its own share link and sign-up page.
- **F-3 (P0)** Sign-up collects name, phone number, optional Telegram handle, a short goal question, and a basic health questionnaire (injuries, medical conditions).
- **F-4 (P0)** After sign-up the person receives the program immediately, and daily reminders run automatically.
- **F-5 (P0)** Before the program ends, the app shows a "continue with me" offer from the trainer with a link to paid plans. The trainer can write the offer text.
- **F-6 (P0)** Leads view shows total sign-ups, active participants, completions, and paid conversions per program.
- **F-7 (P1)** Source tracking: sign-up asks "How did you find this?" so the trainer can see which posts work.
- **F-8 (P1)** Trainer can send a personal message to selected leads, especially those near the end of the program.
- **F-9 (P1)** A cap on the number of free-program leads per trainer, adjustable by the platform, to control messaging and data costs.
- **F-10 (P0)** Leads belong to the trainer. The platform does not use lead contact details for its own marketing.

### 8.3 Paid plans and payments

- **P-1 (P0)** Trainer can create paid plans with a name, price in birr, duration, and what is included (for example one-on-one coaching, group program, or check-in only).
- **P-2 (P0)** Client pays through Telebirr or Chapa-supported methods. The platform collects the payment, deducts its 5% commission, and sends the remainder to the trainer.
- **P-3 (P0)** Clear receipts for clients and clear earnings records for trainers.
- **P-4 (P0)** Payout schedule that trainers understand (for example weekly), with confirmation when payouts are sent.
- **P-5 (P0)** Plan expiry date visible to both client and trainer.
- **P-6 (P1)** Renewal reminders and one-tap renewal.
- **P-7 (P1)** Refund handling: trainer-approved refunds with a recorded reason, and a dispute channel for the platform to mediate.
- **P-8 (P1)** Trainer-defined trial: the first 3 to 5 clients a trainer brings can be commission-free, to lower the barrier to trying the platform.
- **P-9 (P2)** Discount codes and bundle pricing.
- **P-10 (P2)** Installment payments for longer plans.

### 8.4 Program builder and templates

- **B-1 (P0)** Trainer can build a program from days, workouts, and exercises, each with sets, reps, rest times, and notes.
- **B-2 (P0)** A starter library of exercises with short, compressed demo videos and Amharic and English names.
- **B-3 (P0)** Reusable program templates: build once, assign to many clients in one action.
- **B-4 (P0)** Platform-provided starter templates (for example beginner fat loss, home workouts without equipment, beginner strength, mobility) so a trainer can launch quickly.
- **B-5 (P1)** Trainer can edit a program for one client without changing the template.
- **B-6 (P1)** Trainer can upload their own exercise videos and voice instructions.
- **B-7 (P2)** Progression rules, for example increase weight after two successful weeks.

### 8.5 Client management

- **C-1 (P0)** Client list showing each client's plan, program, start and end date, and last activity.
- **C-2 (P0)** A "needs attention" view that surfaces: clients who submitted a check-in awaiting reply, clients who missed several workouts, clients whose plan expires soon, and new clients awaiting a program.
- **C-3 (P0)** Client profile page with goals, health notes, progress history, check-ins, and messages in one place.
- **C-4 (P1)** Tags and notes (for example "knee injury" or "prefers morning").
- **C-5 (P1)** Bulk actions: assign a program or send a message to a selected group.
- **C-6 (P2)** Basic insights, such as clients at risk of leaving based on declining activity.

### 8.6 Client daily experience

- **D-1 (P0)** Client sees one clear "today" screen with the workout, demo clips, sets, and reps.
- **D-2 (P0)** Client can log a workout in a few taps (done, partial, skipped) and add a note.
- **D-3 (P0)** Client can view the program for the coming days.
- **D-4 (P1)** Streaks and simple milestones to encourage consistency.
- **D-5 (P1)** Videos download or buffer for offline or low-connection use.
- **D-6 (P1)** Language toggle between Amharic and English.

### 8.7 Check-ins and feedback

- **K-1 (P0)** Weekly check-in form: weight (optional), a note, and optional photos or a short video or voice message.
- **K-2 (P0)** Trainer can reply with text or a voice note.
- **K-3 (P0)** Photos and videos are compressed automatically before upload.
- **K-4 (P1)** Customizable check-in questions per trainer.
- **K-5 (P1)** Progress view for clients: weight, photos, and workout streaks over time.
- **K-6 (P2)** Quick reply templates for common feedback to save trainer time.

### 8.8 Group programs

- **G-1 (P1)** Trainer can run a group program at a lower price: same plan for everyone, a shared group chat, and an optional weekly challenge.
- **G-2 (P1)** Group size limit set by the trainer.
- **G-3 (P1)** Group announcements, such as weekly updates or motivational messages.
- **G-4 (P2)** Leaderboards or consistency badges within a group.

### 8.9 Messaging and reminders

- **M-1 (P0)** In-app messaging between trainer and client, supporting text and voice notes.
- **M-2 (P0)** Reminders for workouts, check-ins, and renewals, delivered by push notification and by SMS or Telegram where possible.
- **M-3 (P1)** Telegram integration so clients can receive reminders and quick-reply without opening the app.
- **M-4 (P1)** Trainer controls how often and when automated messages are sent, with quiet hours.
- **M-5 (P0)** Clients can opt out of reminders at any time.

### 8.10 Earnings and reporting

- **E-1 (P0)** Earnings screen showing money received this month, platform commission deducted, pending payouts, and paid clients.
- **E-2 (P0)** List of clients with payment status (paid, due soon, overdue).
- **E-3 (P1)** Simple monthly summary: new clients, renewals, churn, and revenue.
- **E-4 (P1)** Export of earnings to a spreadsheet for the trainer's records.

### 8.11 Admin tools (internal)

- **A-1 (P0)** View and search trainers, clients, payments, and payouts.
- **A-2 (P0)** Ability to suspend a trainer or client account.
- **A-3 (P0)** Manage platform template library and exercise library.
- **A-4 (P1)** Review dispute and refund requests.
- **A-5 (P1)** Trainer verification workflow.
- **A-6 (P1)** Dashboard of platform health: active trainers, active clients, revenue, commission, and conversion rates.

## 9. Monetization

**Model.** 5% commission on every payment processed through the platform. No subscription fee in version 1.

**Details to confirm during the pilot:**

- Exact Telebirr and Chapa transaction fees, and who bears them. The platform's margin after these fees determines whether 5% is sustainable.
- Whether to introduce a minimum fee per transaction so very low-priced plans remain worthwhile.
- Whether free or commission-free introductory allowances (for example the first 3 to 5 clients) increase trainer sign-ups enough to justify the lost revenue.
- Whether to add an optional paid trainer plan later (advanced analytics, branded page, bulk tools) to increase revenue per trainer.

**Illustrative economics.** If an average trainer has 20 clients paying 600 birr each per month, the trainer earns 12,000 birr gross and the platform earns 600 birr before payment fees. At that rate the platform needs roughly 150 to 200 such trainers to cover a small team's costs. This needs to be recalculated with real pilot data.

**Protecting commission.** The platform collects payments for plans so the commission is deducted automatically. Product value (templates, check-ins, tracking, renewals, leads) is the main reason trainers stay, and no feature should depend on punishing trainers who take payments elsewhere.

## 10. Localization and Ethiopia-specific requirements

- **Languages:** Amharic and English at launch. Oromo and others are later candidates.
- **Currency:** Ethiopian birr only. Pricing displayed clearly with no hidden fees.
- **Payments:** Telebirr and Chapa-supported methods as the primary options. Consider bank transfer support later.
- **Connectivity:** Compress images and videos, allow resumable uploads, and keep pages light. Prefer voice notes and short videos over live video.
- **Channels:** Telegram and SMS reminders in addition to app notifications, since many users check them more reliably.
- **Device range:** The mobile web app should work well on low-end Android phones and common browsers.
- **Cultural fit:** Exercise content and photo features should respect local norms. Photo sharing is optional, private by default, and visible only to the client's own trainer.
- **Inflation:** Prices are set by trainers, and the commission is percentage-based, so revenue follows inflation without changes to the product.

## 11. Non-functional requirements

- **Performance:** Key pages load within a few seconds on a typical Ethiopian mobile connection.
- **Reliability:** Payment and payout records must be accurate and auditable. No payment should be lost or double-counted.
- **Security:** Secure storage of personal and payment-related data, role-based access, and protection against account takeover.
- **Accessibility:** Readable fonts and good contrast, and screens usable with one hand on a small phone.
- **Scalability:** Designed to handle thousands of trainers and tens of thousands of clients without redesign.
- **Support:** A way for trainers and clients to contact support through Telegram or phone, with responses in Amharic and English.

## 12. Privacy, safety, and trust

- **Data ownership:** Clients and leads belong to the trainer. The platform uses data only to run the service.
- **Consent:** Clear consent at sign-up covering how contact details, health information, and photos are used.
- **Data minimization:** Collect only what is needed. Health information and photos are visible only to the client and their trainer.
- **Regulation:** Review Ethiopian personal data protection requirements and payment regulations (National Bank of Ethiopia rules for payment handling) before launch, and get qualified legal advice.
- **Health safety:** Clients complete a basic health questionnaire. Trainers see warnings (for example injuries or medical conditions). The app displays a clear notice that coaching is not medical advice and encourages clients with medical conditions to consult a doctor.
- **Trainer quality:** Optional verification badge for trainers with checked credentials. Ability for clients to report problems.
- **Messaging abuse:** Controls to prevent trainers from spamming leads, and a simple way for people to opt out.
- **Content rules:** Clear rules against harmful advice (extreme diets, unsafe training for minors, banned substances) with the ability to remove content and suspend accounts.
- **Minors:** Define a minimum age for client accounts and handle under-18 users carefully, for example requiring parent consent.

## 13. Success metrics

### North-star metric

**Paying clients per active trainer.** This measures whether trainers actually scale, which is the core promise of the product.

### Supporting metrics

**Trainer side**

- Trainers onboarded and trainers active each week.
- Time from sign-up to first shared link.
- Free programs created per trainer.
- Trainer retention at 30 and 90 days.
- Trainer-reported hours saved, collected through surveys.

**Lead and conversion**

- Free program sign-ups per trainer.
- Free program completion rate.
- Conversion from free program to paid client.
- Cost to the platform per free lead (messaging and data).

**Client side**

- Workout completion rate.
- Weekly check-in rate.
- Client retention at 30, 60, and 90 days.
- Renewal rate.

**Business**

- Gross payment volume.
- Net commission after payment fees.
- Share of trainer payments flowing through the platform versus outside it (based on trainer surveys).

### Pilot targets (to be adjusted with real data)

- 5 to 10 trainers onboarded in the first pilot.
- Each trainer with at least 10 paying clients on the platform by the end of the pilot.
- Free-to-paid conversion above 5 to 10%.
- At least 60% of clients still active after 30 days.
- At least 70% of pilot trainers say they would pay for the product again and recommend it.

## 14. Release plan

### Phase 0: Manual pilot (weeks 1 to 6)

- Recruit 5 to 10 trainers in Addis Ababa.
- Run free programs and paid coaching using simple tools: Telegram bot or groups, spreadsheets, and a Telebirr or Chapa payment link.
- Learn what trainers actually need, what clients pay, and how conversion works.
- Interview trainers weekly. Record prices, client counts, and time spent.

**Exit criteria:** Trainers see clear value, clients pay, and conversion data supports building.

### Phase 1: MVP (months 2 to 4)

Features: T-1 to T-4, F-1 to F-6, F-10, P-1 to P-5, B-1 to B-4, C-1 to C-3, D-1 to D-3, K-1 to K-3, M-1, M-2, M-5, E-1, E-2, A-1 to A-3.

- Mobile-friendly web app in Amharic and English.
- Launch with the pilot trainers first, then invite more.

**Exit criteria:** Pilot trainers run their coaching on the platform instead of chat apps, and revenue through the platform is growing weekly.

### Phase 2: Growth features (months 4 to 8)

Features: remaining P1 items including group programs, Telegram integration, renewals, refunds, source tracking, check-in customization, progress views, verification, and earnings summaries.

- Grow trainer numbers, starting in Addis Ababa.
- Test onboarding through trainer communities and gyms.

### Phase 3: Expansion (month 8 onward)

- Consider a native mobile app, trainer subscriptions for premium tools, other cities, additional languages, and adjacent coaching categories.
- Consider a public discovery directory only once there is a large base of verified trainers.

## 15. Out of scope for now

- Public trainer marketplace and search.
- Live video sessions and a built-in video calling system.
- Meal planning and food logging.
- Wearable and fitness tracker integrations.
- AI-generated workout plans (worth exploring later as a trainer assistant).
- Selling physical products or equipment.
- Payments in foreign currency.

## 16. Risks and mitigations

- **Small early market.** Smartphone use and disposable income are concentrated in cities. _Mitigation:_ focus on Addis Ababa, size the market honestly, and keep costs low.
- **Trainers do not bring enough clients.** _Mitigation:_ free programs and lead tools, starter templates, and coaching for trainers on how to promote.
- **Free leads do not convert.** _Mitigation:_ test with real trainers in Phase 0, refine the offer timing and pricing, and let trainers follow up personally.
- **Payment leakage.** Trainers collect money outside the platform. _Mitigation:_ make the platform clearly useful, collect payments automatically, and offer the first few clients commission-free to build habit.
- **Thin margins after payment fees.** _Mitigation:_ confirm fee rates early, add a minimum fee, and introduce paid trainer plans later.
- **Quality drops as trainers scale.** _Mitigation:_ tools that preserve personalization, limits on group size, and clear client feedback.
- **Health and safety incidents.** _Mitigation:_ health questionnaire, disclaimers, trainer verification, and reporting tools.
- **Connectivity and data costs.** _Mitigation:_ compressed media, asynchronous coaching, lightweight pages.
- **Regulatory change in payments or data protection.** _Mitigation:_ seek legal advice early and keep payment handling through licensed providers.
- **Competitors or copycats.** A global tool localizes, or a local rival launches. _Mitigation:_ win trainer relationships and distribution early, and keep improving the product around what trainers ask for.
- **Platform cost of free leads.** Messaging and storage for free users cost money without revenue. _Mitigation:_ cap free leads per trainer and compress everything.

## 17. Open questions

1. What do Ethiopian online trainers charge per client per month today, and what would they charge at group or lower-priced tiers?
2. What are the exact Telebirr and Chapa fees and payout timelines, and which provider is better for the first release?
3. Do trainers prefer to receive payouts to Telebirr or to bank accounts?
4. Should the product start as a Telegram bot, a mobile web app, or both?
5. What is the minimum viable template and exercise library, and who produces the demo videos in Amharic and English?
6. How should the platform handle clients under 18?
7. What legal registrations and licenses are needed to collect and distribute payments, and to hold user data?
8. Should commission-free introductory clients be included at launch?
9. What name and brand will the product use?
10. Who handles customer support in the first phase, and in which channels?

## 18. Appendix: Screen list

### Trainer screens

1. Sign up and verify phone
2. Profile setup
3. Payout setup
4. Dashboard (needs-attention list, quick stats)
5. Client list
6. Client profile (progress, check-ins, messages)
7. Program library and templates
8. Program builder
9. Free programs list and leads view
10. Paid plans setup
11. Messages
12. Earnings and payouts
13. Settings (language, reminders, quiet hours)

### Client screens

1. Trainer page or free program landing page
2. Sign-up with health and goal questionnaire
3. Payment (Telebirr or Chapa)
4. Today's workout
5. Workout log
6. Weekly check-in
7. Progress view
8. Messages with trainer
9. Plan and renewal
10. Settings (language, reminders)

### Admin screens

1. Trainers and clients search
2. Payments and payouts
3. Disputes and refunds
4. Template and exercise library management
5. Verification queue
6. Platform dashboard
