/* Poolhall UI kit v2 — Sector template, Candidate hub, Delivery, Blog, secondary pages */

function Breadcrumb({ go, trail }) {
  return (
    <div className="crumb" style={{ color: "var(--steel-200)", paddingTop: 0 }}>
      {trail.map((t, i) => (
        <React.Fragment key={i}>
          {t.go ? <a href="#" onClick={e => { e.preventDefault(); go(t.go); }}>{t.label}</a> : <span>{t.label}</span>}
          {i < trail.length - 1 && <Icon name="chevron-right" />}
        </React.Fragment>
      ))}
    </div>
  );
}

/* ---- Sector page (Construction worked example, §9.5) ---- */
function SectorPage({ go, sectorKey }) {
  const D = window.PH_DATA;
  const map = { con: "Construction", man: "Manufacturing", dig: "Digital" };
  const name = map[sectorKey] || "Construction";
  const meta = window.PH_SECTOR[name];
  const jobs = D.jobs.filter(j => j.sector === name);
  const roles = {
    Construction: ["Project & Site Managers", "Quantity Surveyors", "Site Engineers", "Skilled Trades & Operatives", "M&E / HVAC Engineers", "Estimators"],
    Manufacturing: ["Welders & Fabricators", "CNC Machinists", "Production Operatives", "Maintenance Engineers", "Quality Inspectors", "Production Managers"],
    Digital: ["Paid Media & PPC", "SEO Specialists", "Content & Social", "Designers", "Developers", "Marketing Managers"],
  }[name];
  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={meta.img()} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Breadcrumb go={go} trail={[{ label: "Home", go: "home" }, { label: "Sectors" }, { label: name }]} />
          <Label dark idx="//">Sector</Label>
          <h1>{name} recruitment</h1>
          <p className="lead">{meta && D.sectors.find(s => s.name === name).blurb}</p>
        </div>
      </div>

      <section className="section">
        <div className="container">
          <div className="split">
            <div>
              <Label idx="01 /">Roles we place</Label>
              <h2 className="h2">Talent across the whole {name.toLowerCase()} chain.</h2>
              <p className="lead" style={{ marginTop: 14 }}>From hands-on roles through to leadership, we help across the whole {name.toLowerCase()} chain, with consultants who genuinely understand the work.</p>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginTop: 22 }}>
                {roles.map(r => (
                  <div key={r} style={{ display: "flex", gap: 10, alignItems: "center", fontFamily: "var(--font-mono)", fontSize: 13.5, color: "var(--slate-800)", borderLeft: "2px solid var(--hivis-500)", paddingLeft: 12 }}>{r}</div>
                ))}
              </div>
            </div>
            <div className="media"><img src={meta.img()} alt={name} onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
          </div>
        </div>
      </section>

      <section className="section bg-alt" style={{ background: "#fff" }}>
        <div className="container">
          <div className="points">
            <div className="point"><div className="pi">// 01</div><h3>We know the market</h3><p>Real sector knowledge means we understand the role, the rates and the right fit, first time.</p></div>
            <div className="point"><div className="pi">// 02</div><h3>A network that runs deep</h3><p>Years of relationships across {name.toLowerCase()} mean we reach people the job boards never see.</p></div>
            <div className="point"><div className="pi">// 03</div><h3>Compliance handled</h3><p>Right-to-work, certs and checks managed properly, so you can hire with confidence.</p></div>
            <div className="point"><div className="pi">// 04</div><h3>A focused shortlist</h3><p>We send you a carefully chosen handful of strong candidates, so you can review them in the time you've got.</p></div>
          </div>
        </div>
      </section>

      {jobs.length > 0 && (
        <section className="section">
          <div className="container">
            <SectionHead idx="02 /" label="Open roles" title={"Live " + name + " jobs"}
              action={<Button variant="ghost" iconRight="arrow-right" onClick={() => go("jobs")}>All jobs</Button>} />
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 22 }}>
              {jobs.map(j => <JobCard key={j.id} job={j} go={go} />)}
            </div>
          </div>
        </section>
      )}
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Candidate hub (§9.6) ---- */
function CandidateHub({ go }) {
  const guides = [
    { icon: "clipboard-list", t: "Registration Guide", d: "What to expect when you register and how we'll work with you." },
    { icon: "messages-square", t: "Interview Tips", d: "Practical prep that actually helps you land the role." },
    { icon: "file-text", t: "CV Tips", d: "Make your experience count, straight from the consultants who read CVs all day." },
  ];
  return (
    <main>
      <div className="pagehead photo">
        <img src={window.PH_IMG.candidateHero} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">For candidates</Label>
          <h1 style={{ maxWidth: "16ch" }}>Find work that fits.</h1>
          <p className="lead">Register once and we'll match you to roles worth your time, with honest advice the whole way through.</p>
          <div style={{ display: "flex", gap: 12, marginTop: 26, flexWrap: "wrap" }}>
            <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("jobs")}>View jobs</Button>
            <Button variant="ghost-light" size="lg" onClick={() => go("register")}>Register your CV</Button>
          </div>
        </div>
      </div>

      <section className="section">
        <div className="container">
          <div className="split">
            <div>
              <Label idx="01 /">How it works</Label>
              <h2 className="h2">Three steps to your next role.</h2>
              <ul className="checklist" style={{ marginTop: 20 }}>
                <li><Icon name="check-circle" /> <strong>Register</strong> — send your CV and tell us what a good next move looks like.</li>
                <li><Icon name="check-circle" /> <strong>We match you</strong> — only to roles that genuinely fit, so every introduction feels worthwhile.</li>
                <li><Icon name="check-circle" /> <strong>We guide you</strong> — interview prep and honest feedback through to your first day.</li>
              </ul>
              <Button variant="primary" style={{ marginTop: 24 }} iconRight="arrow-right" onClick={() => go("register")}>Register now</Button>
            </div>
            <div className="media"><img src={window.PH_IMG.aboutA} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
          </div>
        </div>
      </section>

      <section className="section bg-alt">
        <div className="container">
          <SectionHead idx="02 /" label="Guides & advice" title="Help when you need it" center />
          <div className="svc-grid">
            {guides.map((g, i) => (
              <div className="svc" key={g.t}>
                <div className="svc-photo"><img src={window.PH_SVCIMG(i + 2)} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <span className="si"><Icon name={g.icon} /></span>
                <h3>{g.t}</h3><p>{g.d}</p>
                <button className="btn-text" onClick={() => go("blog")}>Read guide <Icon name="arrow-right" /></button>
              </div>
            ))}
          </div>
        </div>
      </section>
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Delivery options (§9.7) ---- */
function Delivery({ go }) {
  const services = [
    { n: "01", icon: "users", t: "Temporary", d: "Flexible, compliant workforce when you need to scale up fast. Vetted, paid and managed by us." },
    { n: "02", icon: "user-check", t: "Permanent", d: "Full end-to-end search for permanent hires, sourced, screened and shortlisted to a brief." },
    { n: "03", icon: "trending-up", t: "Scale", d: "Volume and project recruitment for growth phases, multiple hires through one accountable partner." },
    { n: "04", icon: "calendar-clock", t: "Pay Monthly", d: "Spread the cost of a permanent placement across manageable monthly payments." },
    { n: "05", icon: "hard-hat", t: "On-Site", d: "A fully managed on-site service for high-volume, single-location requirements." },
  ];
  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={window.PH_IMG.sectorCon} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Breadcrumb go={go} trail={[{ label: "Home", go: "home" }, { label: "Employers", go: "employers" }, { label: "Delivery Options" }]} />
          <Label dark idx="//">For employers</Label>
          <h1>Recruitment Delivery Options</h1>
          <p className="lead">Five ways to work together, all with the same care and quality. Whichever suits you best, you're in good hands.</p>
        </div>
      </div>
      <section className="section">
        <div className="container">
          <div className="svc-grid">
            {services.slice(0, 3).map((s, i) => (
              <div className={"svc" + (i === 1 ? " dark" : "")} key={s.t}>
                <div className="svc-photo"><img src={window.PH_SVCIMG(i)} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="num">{s.n}</div><span className="si"><Icon name={s.icon} /></span>
                <h3>{s.t}</h3><p>{s.d}</p>
                <button className="btn-text" style={i === 1 ? { color: "var(--hivis-400)" } : {}} onClick={() => go("contact")}>Enquire <Icon name="arrow-right" /></button>
              </div>
            ))}
          </div>
          <div className="svc-grid" style={{ gridTemplateColumns: "1fr 1fr", marginTop: 22 }}>
            {services.slice(3).map((s, i) => (
              <div className="svc" key={s.t}>
                <div className="svc-photo"><img src={window.PH_SVCIMG(i + 3)} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="num">{s.n}</div><span className="si"><Icon name={s.icon} /></span>
                <h3>{s.t}</h3><p>{s.d}</p>
                <button className="btn-text" onClick={() => go("contact")}>Enquire <Icon name="arrow-right" /></button>
              </div>
            ))}
          </div>
        </div>
      </section>
      <PhotoBand img={window.PH_IMG.sectorMan} kicker="Not sure which fits?"
        title="Tell us the brief and we'll point you the right way."
        text="A single conversation is usually all it takes. We'll give you our honest take on what's likely to work for your role, your timeline and your budget.">
        <Button variant="primary" iconRight="arrow-right" onClick={() => go("contact")}>Talk to us</Button>
      </PhotoBand>
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Blog (§9.9) ---- index + post share one data source ---- */
const BLOG_POSTS = [
  { id: "advert", img: () => window.PH_IMG.blog1, cat: "Hiring", date: "12 Jun 2026", read: "5 min", t: "How to write a job advert that actually attracts the right people", ex: "Most adverts repel good candidates without realising it. Here's what to do instead." },
  { id: "interview", img: () => window.PH_IMG.blog2, cat: "Candidates", date: "04 Jun 2026", read: "4 min", t: "Interview prep: the five questions to always have ready", ex: "Walk in calm and prepared with this simple framework." },
  { id: "skills-gap", img: () => window.PH_IMG.blog3, cat: "Sector", date: "28 May 2026", read: "6 min", t: "The West Midlands manufacturing skills gap, and how to hire around it", ex: "Where the talent actually is, and how to reach it." },
];
function Blog({ go }) {
  const cats = ["All", "Hiring", "Candidates", "Sector"];
  const [cat, setCat] = useState("All");
  const list = BLOG_POSTS.filter(p => cat === "All" || p.cat === cat);
  return (
    <main>
      <div className="pagehead photo">
        <img src={window.PH_IMG.sectorDig} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">News &amp; insight</Label>
          <h1>The Poolhall blog</h1>
          <p className="lead">Practical advice on hiring and finding work, from a team that does it every day.</p>
        </div>
      </div>
      <section className="section">
        <div className="container">
          <div style={{ display: "flex", gap: 8, marginBottom: 32, flexWrap: "wrap" }}>
            {cats.map(c => (
              <button key={c} onClick={() => setCat(c)}
                style={{ fontFamily: "var(--font-mono)", fontSize: 12.5, letterSpacing: ".04em", padding: "8px 14px", borderRadius: "var(--r-sm)", cursor: "pointer",
                  border: "1.5px solid " + (cat === c ? "var(--slate-900)" : "var(--border-strong)"), background: cat === c ? "var(--slate-900)" : "#fff", color: cat === c ? "#fff" : "var(--mist-500)" }}>{c}</button>
            ))}
          </div>
          <div className="blog-grid">
            {list.map(p => (
              <article className="blog" key={p.id} onClick={() => go("post", p)}>
                <div className="bimg"><span className="cat">{p.cat}</span><img src={p.img()} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="bbody">
                  <div className="bdate">{p.date} · {p.read} read</div>
                  <h3>{p.t}</h3>
                  <p>{p.ex}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Blog post template (§9.9) ---- */
function BlogPost({ go, post }) {
  const p = post || BLOG_POSTS[0];
  const related = BLOG_POSTS.filter(x => x.id !== p.id).slice(0, 2);
  return (
    <main>
      <div className="pagehead photo">
        <img src={p.img()} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Breadcrumb go={go} trail={[{ label: "Home", go: "home" }, { label: "Blog", go: "blog" }, { label: p.cat }]} />
          <div style={{ display: "flex", gap: 12, alignItems: "center", margin: "6px 0 14px", fontFamily: "var(--font-mono)", fontSize: 12.5, color: "var(--fg-on-dark-2)", letterSpacing: ".04em" }}>
            <span style={{ background: "var(--hivis-500)", color: "var(--slate-900)", padding: "4px 9px", borderRadius: "var(--r-sm)", textTransform: "uppercase", fontSize: 10.5, letterSpacing: ".1em" }}>{p.cat}</span>
            <span>{p.date}</span><span>·</span><span>{p.read} read</span>
          </div>
          <h1 style={{ maxWidth: "20ch" }}>{p.t}</h1>
        </div>
      </div>
      <section className="section">
        <div className="container" style={{ maxWidth: 760 }}>
          <div className="job-body" style={{ padding: 0 }}>
            <p className="lead" style={{ color: "var(--ink-900)", fontWeight: 500, marginBottom: 8 }}>{p.ex}</p>
            <p>Recruitment isn't complicated, but it is easy to get wrong. After years placing people across Construction, Manufacturing and Digital, the same handful of mistakes come up again and again, and they're all avoidable.</p>
            <h2>Start with the person, not the spec</h2>
            <p>A list of requirements tells a candidate what you want from them. It says nothing about what they'd get from you. The best adverts lead with the work, the team and the reason the role exists, then get to the essentials.</p>
            <h2>Be specific about the things that matter</h2>
            <p>Salary, location and working pattern aren't details to bury, they're the first things a good candidate checks. Put them up front, in plain terms. Vague ranges and "competitive salary" cost you applications.</p>
            <ul>
              <li>Say what the salary actually is.</li>
              <li>Be honest about onsite, hybrid or remote.</li>
              <li>Name the sector and the level clearly.</li>
            </ul>
            <h2>Then make it easy to act</h2>
            <p>One clear next step, one short form, no hoops. The faster a good candidate can raise their hand, the more of them you'll hear from.</p>
            <div style={{ display: "flex", alignItems: "center", gap: 14, marginTop: 36, padding: "20px 0", borderTop: "1px solid var(--border)", borderBottom: "1px solid var(--border)" }}>
              <span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--mist-400)", letterSpacing: ".1em", textTransform: "uppercase" }}>Share</span>
              {["linkedin", "twitter", "link"].map(s => <a key={s} href="#" onClick={e => e.preventDefault()} style={{ width: 38, height: 38, border: "1px solid var(--border)", borderRadius: "var(--r-sm)", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--slate-700)" }}><Icon name={s === "twitter" ? "twitter" : s === "link" ? "link" : "linkedin"} /></a>)}
            </div>
          </div>
        </div>
      </section>
      <section className="section tight bg-alt">
        <div className="container">
          <SectionHead idx="//" label="Keep reading" title="Related articles" />
          <div className="blog-grid" style={{ gridTemplateColumns: "1fr 1fr" }}>
            {related.map(r => (
              <article className="blog" key={r.id} onClick={() => go("post", r)}>
                <div className="bimg"><span className="cat">{r.cat}</span><img src={r.img()} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="bbody"><div className="bdate">{r.date} · {r.read} read</div><h3>{r.t}</h3><p>{r.ex}</p></div>
              </article>
            ))}
          </div>
        </div>
      </section>
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Legal / long-form template (§9.9) ---- */
const LEGAL = {
  privacy: { title: "Privacy Policy", updated: "Updated 1 June 2026", intro: "How Poolhall Recruitment collects, uses and protects your personal data.",
    sections: [["1. Who we are", "Poolhall Recruitment Ltd (company 13319338), Grosvenor House, 11 St Pauls Square, Birmingham B3 1RB. We are the data controller for the information you share with us."], ["2. What we collect", "Contact details, your CV and the information in it, and records of our conversations. For candidates, this is what we need to represent you for roles."], ["3. How we use it", "To match you to roles, submit applications on your behalf with your consent, and keep you updated. We never sell your data."], ["4. Lawful basis", "We rely on legitimate interest and, where required, your explicit consent, which you can withdraw at any time."], ["5. Your rights", "You can ask to access, correct or delete your data, or object to its use. Email hello@poolhallrecruitment.co.uk and we'll respond within one month."]] },
  terms: { title: "General Terms", updated: "Updated 1 June 2026", intro: "The terms that apply when you use this website and our services.",
    sections: [["1. Using this site", "You may use this site for lawful purposes only. Job listings are provided in good faith and may change or close at any time."], ["2. Applications", "Applying expresses interest, it does not guarantee an interview or offer. Decisions rest with the hiring employer."], ["3. Liability", "We take care to keep information accurate but cannot accept liability for decisions made solely on the basis of this site."], ["4. Governing law", "These terms are governed by the law of England and Wales."]] },
  cookies: { title: "Cookie Policy", updated: "Updated 1 June 2026", intro: "How and why this site uses cookies.",
    sections: [["1. Essential cookies", "Required for the site to work, you cannot turn these off."], ["2. Analytics", "Optional cookies that help us understand how the site is used, set only with your consent."], ["3. Managing cookies", "You can change your preferences at any time via the cookie banner or your browser settings."]] },
};
function Legal({ go, pageId }) {
  const p = LEGAL[pageId] || LEGAL.privacy;
  return (
    <main className="bg-alt">
      <div className="pagehead">
        <div className="container">
          <Breadcrumb go={go} trail={[{ label: "Home", go: "home" }, { label: "Legal" }, { label: p.title }]} />
          <h1>{p.title}</h1>
          <p className="lead">{p.intro}</p>
          <p style={{ fontFamily: "var(--font-mono)", fontSize: 12.5, color: "var(--steel-200)", marginTop: 12 }}>{p.updated}</p>
        </div>
      </div>
      <section className="section">
        <div className="container" style={{ maxWidth: 760 }}>
          <div className="job-body" style={{ padding: 0 }}>
            {p.sections.map((s, i) => (
              <React.Fragment key={i}><h2>{s[0]}</h2><p>{s[1]}</p></React.Fragment>
            ))}
          </div>
        </div>
      </section>
      <CtaBands go={go} />
    </main>
  );
}

/* ---- Generic secondary page (Why Us, Bespoke, HR, Commitment, guides) ---- */
const SECONDARY = {
  "why-us": { eyebrow: "For employers", title: "Why Us", lead: "What an independent, experienced team does a little differently, and why it matters to your hire.",
    points: [["Senior by default", "You'll work with experienced consultants who take the time to understand your brief."], ["Genuinely independent", "No franchise targets pulling our focus away from your role."], ["Honest advice", "Straight talk on salary, market and timelines."], ["Long-term relationships", "We're here for your next hire, and the one after."]] },
  "bespoke": { eyebrow: "For employers", title: "Bespoke Search", lead: "Discreet, targeted search for the senior roles you really want to get right.",
    points: [["Mapped to a brief", "We build a target list, not a job-board post."], ["Confidential", "Sensitive and senior searches handled discreetly."], ["Thorough vetting", "Deep screening before anyone reaches your desk."], ["Market intelligence", "Real insight on who's out there and what they cost."]] },
  "bja": { eyebrow: "For employers", title: "Better Job Adverts", lead: "Keep hiring in-house and let us make your advert work harder. One fixed fee, your brand front and centre, a shortlist that's ready to interview. Pricing confirmed when you enquire.",
    points: [["One simple fixed fee", "No surprises. Pricing confirmed when you enquire."], ["Multi-board reach", "Your role, posted across the boards that matter."], ["Branded adverts", "Written and presented properly, as your business."], ["CV screening", "We sift so you only see the candidates worth your time."]] },
  "hr": { eyebrow: "For employers", title: "HR Services", lead: "Support that goes beyond the hire, for businesses without a full HR function.",
    points: [["Onboarding support", "Help your new starter land well."], ["Contracts & compliance", "The paperwork done properly."], ["Retention advice", "Keep the good people you worked hard to hire."], ["A partner on call", "Someone to ask when an HR question lands."]] },
  "commitment": { eyebrow: "Compliance", title: "Poolhall's Commitment", lead: "Doing recruitment ethically isn't a tagline, it's how we operate. Our policies, in plain English.",
    points: [["Modern Slavery", "Our statement and the checks we run."], ["Complaints Procedure", "How to raise a concern and what happens next."], ["Candidate Registration", "Exactly how we handle your data and consent."], ["Equal opportunities", "Fair process for every candidate, every time."]] },
};
function Secondary({ go, pageId }) {
  const p = SECONDARY[pageId] || SECONDARY["why-us"];
  const SEC_IMG = {
    "why-us": window.PH_IMG.heroTeam, "bespoke": window.PH_IMG.aboutA, "bja": window.PH_IMG.sectorDig,
    "hr": window.PH_IMG.heroTeam, "commitment": window.PH_IMG.aboutA,
  };
  const hImg = SEC_IMG[pageId] || window.PH_IMG.heroTeam;
  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={hImg} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">{p.eyebrow}</Label>
          <h1>{p.title}</h1>
          <p className="lead">{p.lead}</p>
        </div>
      </div>
      <section className="section">
        <div className="container">
          <div className="points">
            {p.points.map((pt, i) => (
              <div className="point" key={i}><div className="pi">// {String(i + 1).padStart(2, "0")}</div><h3>{pt[0]}</h3><p>{pt[1]}</p></div>
            ))}
          </div>
        </div>
      </section>
      <PhotoBand img={window.PH_IMG.sectorMan} kicker={p.eyebrow}
        title="Let's chat about what you need."
        text="A quick, friendly conversation with a senior consultant who'll really listen. We'll give you an honest, no-pressure view on how we can help.">
        <Button variant="primary" iconRight="arrow-right" onClick={() => go("contact")}>Get in touch</Button>
      </PhotoBand>
      <CtaBands go={go} />
    </main>
  );
}

Object.assign(window, { SectorPage, CandidateHub, Delivery, Blog, BlogPost, Legal, Secondary, Breadcrumb });
