export const trustedLogos = ['KAZA PR', 'BRASA'];

export const services = [
    {
        name: 'Copywriting',
        desc: 'Persuasive marketing copy that converts visitors into paying customers.',
    },
    {
        name: 'Ghostwriting',
        desc: 'Thought leadership content for founders, CEOs, and personal brands.',
    },
    {
        name: 'Blog Writing',
        desc: 'SEO-friendly, engaging posts that drive organic traffic consistently.',
    },
    {
        name: 'Newsletters',
        desc: 'Readable, actionable email content that boosts opens and retention.',
    },
    {
        name: 'Press Releases',
        desc: 'News-ready releases that get your announcements noticed by media.',
    },
    {
        name: 'Website Copy',
        desc: 'Clear, conversion-focused pages that communicate your value fast.',
    },
    {
        name: 'Product Descriptions',
        desc: 'Benefits-led descriptions that make products impossible to ignore.',
    },
    {
        name: 'LinkedIn Content',
        desc: 'Professional posts and threads that grow your network and influence.',
    },
    {
        name: 'E-Book Writing',
        desc: 'Engaging, informative e-books that educate and inspire your audience.',
    },
];

export const steps = [
    {
        num: '01',
        title: 'Subscribe',
        active: false,
        desc: 'Choose your plan and get access within the hour to your own Trello board.',
        feats: ['No contracts', 'Cancel anytime'],
    },
    {
        num: '02',
        title: 'Submit requests',
        active: true,
        desc: 'Send writing tasks directly through your personal Trello board.',
        feats: ['Request management', 'Status tracking'],
    },
    {
        num: '03',
        title: 'Receive drafts',
        active: false,
        desc: 'Get polished, publish-ready drafts delivered in a few business days.',
        feats: ['Fast turnaround', 'High quality'],
    },
    {
        num: '04',
        title: 'Revise and approve',
        active: false,
        desc: 'Request edits, leave comments, and keep everything moving forward.',
        feats: ['Unlimited revisions', 'Direct feedback'],
    },
];

// export const plans = [
//     {
//         name: 'Starter',
//         price: '$499',
//         featured: false,
//         desc: 'Perfect for growing brands that need consistent, quality content.',
//         feats: [
//             'Maximum of 4,000 words per request',
//             'Unlimited total requests',
//             '24-72 hr turnaround',
//             'Unlimited revisions',
//             'Dedicated Trello board',
//             'All content types',
//         ],
//     },
//     {
//         name: 'Pro',
//         price: '$899',
//         featured: true,
//         desc: 'Our most popular plan for brands publishing multiple times a week.',
//         feats: [
//             'Maximum 10,000 words per request',
//             'Unlimited total requests',
//             '24-48 hr turnaround',
//             'Unlimited revisions',
//             'Priority queue',
//             'SEO optimization included',
//             'Brand voice guidelines',
//         ],
//     },
//     {
//         name: 'Growth',
//         price: '$1,499',
//         featured: false,
//         desc: 'For high-volume teams that need a constant stream of premium content.',
//         feats: [
//             'Unlimited words per request',
//             'Unlimited total requests',
//             'Same-day turnaround',
//             'Unlimited revisions',
//             'Dedicated Slack channel',
//             'All content types + strategy',
//         ],
//     },
// ];

export const portfolio = [
    {
        type: 'Sales Page',
        title: 'How Notion scaled from 0 to 1M users with one landing page',
        excerpt:
            'A deep breakdown of conversion principles applied to a now-iconic SaaS sales page...',
    },
    {
        type: 'Email Campaign',
        title: 'The 5-email welcome sequence that drove 40% trial conversions',
        excerpt:
            'Each email in this sequence was designed around a single objection and resolved it with...',
    },
    {
        type: 'Blog Article',
        title: 'The definitive guide to content strategy for B2B founders in 2024',
        excerpt:
            'Most B2B founders treat content as an afterthought. This guide flips that approach entirely...',
    },
    {
        type: 'LinkedIn Post',
        title: "I turned down a $2M acquisition offer. Here's what I learned.",
        excerpt:
            'Three years ago, a strategic buyer offered to acquire our bootstrapped SaaS. The term sheet looked...',
    },
    {
        type: 'Newsletter',
        title: 'The Weekly Brief: 5 things that actually moved our needle this quarter',
        excerpt:
            'Every week, one insight from the trenches of building a bootstrapped product. This week: the...',
    },
    {
        type: 'Press Release',
        title: 'MayWrites Launches Subscription Writing Service for Fast-Growing Brands',
        excerpt:
            'New productized writing service gives brands unlimited content requests for a flat monthly fee...',
    },
    {
        type: 'E-Book',
        title: 'The Ultimate Guide to Content Writing',
        excerpt:
            'A comprehensive guide to content writing, from the basics to the advanced techniques...',
    },
];

export type CompareCell = boolean | 'some';
export type CompareRow = [
    feature: string,
    mayWrites: CompareCell,
    freelancer: CompareCell,
    agency: CompareCell,
];

export const compareRows: CompareRow[] = [
    ['Flat monthly rate', true, false, false],
    ['Unlimited requests', true, false, false],
    ['24-72 business hours', true, false, false],
    ['No contracts or lock-in', true, false, false],
    ['Easy revisions anytime', true, 'some', 'some'],
    ['No meetings required', true, false, true],
    ['Cancel anytime', true, false, false],
    ['Scales with your needs', true, false, false],
];

export const faqs = [
    {
        q: 'What counts as a request?',
        a: 'A request is a single deliverable: one blog post, one press release, one newsletter. If a task has multiple separate pieces, each is submitted as its own request.',
    },
    {
        q: "What's the turnaround time?",
        a: 'Typical turnaround is 24-72 hours per request depending on length and complexity. Longer or research-heavy projects may take more time, and an estimate is provided when you submit.',
    },
    {
        q: 'How do revisions work?',
        a: "Unlimited revisions are included. Leave clear comments on your Trello card and I'll refine until you're completely happy.",
    },
    // {
    //     q: 'Can I pause my subscription?',
    //     a: "Yes. Pause anytime. Billing stops while paused and you can resume whenever you're ready.",
    // },
    {
        q: 'Can I cancel my subscription?',
        a: 'Yes. Cancel anytime through Stripe or by emailing hello@maywrites.co. There are no contracts or lock-ins. Your subscription stops renewing at the end of the current billing period, and you keep access to your Trello board until that period ends.',
    },
    {
        q: 'Do Growth plans include a dedicated Slack channel?',
        a: 'Yes. Growth subscribers can request a private Slack channel for faster communication. After you subscribe, email hello@maywrites.co and we will send your invite.',
    },
    {
        q: 'Do you write in my brand voice?',
        a: "Absolutely. Provide brand guidelines, past samples, audience info, or a short brief and I'll match your tone and voice precisely.",
    },
    {
        q: 'Do you do SEO blog writing?',
        a: 'Of course. SEO-optimized blog posts with keyword research, meta titles, descriptions, and basic on-page optimization are available on all plans.',
    },
    {
        q: 'Is there a limit to requests?',
        a: "No. Submit unlimited requests. They'll be completed one or more at a time, depending on your plan, in the order submitted.",
    },
    {
        q: 'Do you offer refunds?',
        a: "Refunds are not offered for completed work. If you're unhappy, we'll revise until it meets the brief or issue a credit at our discretion.",
    },
    {
        q: 'Do you offer SDLC document development?',
        a: 'Not yet. Service will be incorporated in the up-coming months.',
    },
    {
        q: 'What services do you not offer?',
        a: 'We do not provide legal or medical advice, certified translation, licensed design/illustration, specialized financial auditing, or large-scale software development.',
    },
];

export const frontendNavLinks = [
    { label: 'Services', href: '#services' },
    { label: 'How it works', href: '#how' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'FAQ', href: '#faq' },
];

export const footerLinks = [
    { label: 'Services', href: '#services' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'How it works', href: '#how' },
    { label: 'FAQ', href: '#faq' },
    { label: 'Terms', href: '/terms', external: true },
    { label: 'Contact', href: 'mailto:hello@maywrites.co' },
];
