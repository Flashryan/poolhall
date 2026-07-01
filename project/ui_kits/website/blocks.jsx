/* Poolhall UI kit v2 — content blocks */

function Label({ idx, children, dark }) {
  return <p className={"label" + (dark ? " on-dark" : "")}>{idx && <span className="idx">{idx}</span>} {children}</p>;
}

function SectionHead({ idx, label, title, lead, action, dark, center }) {
  return (
    <div className={"section-head" + (center ? " center" : "")}>
      <div className={dark ? "on-dark" : ""}>
        {label && <Label idx={idx} dark={dark}>{label}</Label>}
        <h2 className={"h2" + (dark ? " on-dark" : "")}>{title}</h2>
        {lead && <p className={"lead" + (dark ? " on-dark" : "")}>{lead}</p>}
      </div>
      {action}
    </div>
  );
}

/* ---- four-stage animated hero (the signature) ---- */
const HERO_WORLDS = [
  { key: "con", label: "Construction", img: () => window.PH_IMG.heroConstruction },
  { key: "man", label: "Manufacturing", img: () => window.PH_IMG.heroManufacturing },
  { key: "dig", label: "Digital", img: () => window.PH_IMG.heroDigital },
  { key: "team", label: "Our Team", img: () => window.PH_IMG.heroTeam },
];
function Hero({ go }) {
  const [active, setActive] = useState(0);
  const reduce = useRef(false);
  useEffect(() => {
    reduce.current = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce.current) return;
    const t = setInterval(() => setActive(a => (a + 1) % HERO_WORLDS.length), 4200);
    return () => clearInterval(t);
  }, []);
  return (
    <section className="hero">
      <div className="hero-stage">
        {HERO_WORLDS.map((w, i) => (
          <div key={w.key} className={"hero-slide" + (i === active ? " on" : "")}>
            <img src={w.img()} alt={w.label} onError={e => { e.target.style.display = "none"; }} />
          </div>
        ))}
      </div>
      <div className="container">
        <div className="hero-inner">
          <Label dark idx="//">Recruitment, done well</Label>
          <h1 className="display on-dark">West Midlands roots.<br /><em>National</em> recruitment reach.</h1>
          <p className="lead on-dark">We help the people who build, make and market British business find their next move, across Construction, Manufacturing and Digital. Independent, down-to-earth and always happy to talk things through.</p>
          <div className="hero-cta">
            <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("candidate")}>Find work</Button>
            <Button variant="ghost-light" size="lg" onClick={() => go("employers")}>Hire talent</Button>
          </div>
        </div>
      </div>
      <div className="worlds">
        <div className="container">
          {HERO_WORLDS.map((w, i) => (
            <div key={w.key} className={"world" + (i === active ? " on" : "")} onClick={() => setActive(i)}>
              <div className="wi">{String(i + 1).padStart(2, "0")}</div>
              <div className="wn">{w.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

/* feature strip overlapping hero */
function FeatureStrip() {
  const items = [
    { icon: "target", t: "Specialist, not generalist", d: "Three sectors we know inside out, so the conversation starts in the right place." },
    { icon: "phone-call", t: "A friendly voice on the phone", d: "Consultants who pick up, take the time to listen, and offer genuine advice." },
    { icon: "map", t: "National reach", d: "Black Country roots, helping people find roles the length of the country." },
  ];
  return (
    <div className="feature-strip">
      <div className="container">
        <div className="grid ticks">
          {items.map(f => (
            <div className="feat" key={f.t}>
              <span className="fi"><Icon name={f.icon} /></span>
              <div><h4>{f.t}</h4><p>{f.d}</p></div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Stars({ n = 5 }) { return <div className="stars">{Array.from({ length: n }).map((_, i) => <Icon key={i} name="star" />)}</div>; }

function JobCard({ job, go }) {
  const F = window.PH_FMT;
  const sec = window.PH_SECTOR[job.sector] || { key: "dig" };
  return (
    <article className={"jobcard" + (job.featured ? " feat" : "")} onClick={() => go("job", job)}>
      <div className="jc-photo">
        {sec.img && <img src={sec.img()} alt="" onError={e => { e.target.style.display = "none"; }} />}
        <span className={"jc-tag " + sec.key}>{job.sector}</span>
        {job.featured && <span className="jc-feat"><Icon name="star" /> Featured</span>}
      </div>
      <h3 className="jc-title">{job.title}</h3>
      <div className="jc-meta">
        <span className="m"><Icon name="map-pin" /> {job.location}, {job.region}</span>
        <span className="m"><Icon name="building" /> {job.work}</span>
        <span className="m"><Icon name="banknote" /> <span className="sal">{F.salary(job)}</span> · {job.type}</span>
      </div>
      <p className="jc-snip">{job.summary}</p>
      <div className="jc-foot">
        <span className="jc-view">View job <Icon name="arrow-right" /></span>
        <span className="jc-ref">#{job.id}</span>
      </div>
    </article>
  );
}

function Carousel({ children, light }) {
  const ref = useRef(null);
  const scroll = dir => { const el = ref.current; if (el) el.scrollBy({ left: dir * el.clientWidth * 0.8, behavior: "smooth" }); };
  return (
    <div className="carousel">
      <div className="carousel-track" ref={ref}>{children}</div>
      <div className="carousel-nav" style={{ marginTop: 22 }}>
        <button className={"cbtn" + (light ? " light" : "")} onClick={() => scroll(-1)} aria-label="Previous"><Icon name="arrow-left" /></button>
        <button className={"cbtn" + (light ? " light" : "")} onClick={() => scroll(1)} aria-label="Next"><Icon name="arrow-right" /></button>
      </div>
    </div>
  );
}

function ReviewCard({ r, glass }) {
  return (
    <div className={"review" + (glass ? " on-glass" : "")}>
      <Stars n={r.rating} />
      <p>"{r.text}"</p>
      <div className="who">
        <span className="av">{r.name.split(" ").map(s => s[0]).join("").slice(0, 2)}</span>
        <span><span className="nm">{r.name}</span><br /><span className="rl">{r.role}{r.date ? " · " + r.date : ""}</span></span>
        <span className="g">G</span>
      </div>
    </div>
  );
}

function SectorTile({ s, go }) {
  const meta = window.PH_SECTOR[s.name];
  return (
    <a className="sector-tile" href="#" onClick={e => { e.preventDefault(); go("sector-" + meta.key); }}>
      <img src={meta.img()} alt={s.name} onError={e => { e.target.style.display = "none"; }} />
      <div className="st-body">
        <div className="st-idx">{s.count} live roles</div>
        <h3>{s.name}</h3>
        <p>{s.blurb}</p>
        <span className="st-go">Explore sector <Icon name="arrow-right" /></span>
      </div>
    </a>
  );
}

/* full-bleed in-body photo band with gradient overlay */
function PhotoBand({ img, kicker, title, text, children }) {
  return (
    <section className="photoband">
      <img src={img} alt="" onError={e => { e.target.style.display = "none"; }} />
      <div className="container">
        <div className="pb-inner">
          {kicker && <Label dark idx="//">{kicker}</Label>}
          <h2 className="h2 on-dark">{title}</h2>
          {text && <p>{text}</p>}
          {children && <div className="acts">{children}</div>}
        </div>
      </div>
    </section>
  );
}

/* candidate + employer CTA bands (paired) */
function CtaBands({ go }) {
  return (
    <div className="cta-bands">
      <div className="cta-band cand">
        <div className="inner">
          <div className="ce">For candidates</div>
          <h2 className="on-dark">Looking for your next role?</h2>
          <p>Register in a couple of minutes and we'll be in touch when something that fits comes along, no pressure, just a friendly heads-up.</p>
          <div className="acts">
            <Button variant="primary" iconRight="arrow-right" onClick={() => go("candidate")}>Register now</Button>
            <Button variant="ghost-light" onClick={() => go("jobs")}>View jobs</Button>
          </div>
        </div>
      </div>
      <div className="cta-band emp">
        <div className="inner">
          <div className="ce">For employers</div>
          <h2 className="on-dark">Looking to hire?</h2>
          <p>Tell us who you're looking for and we'll put together a shortlist of people we think you'll be glad to meet.</p>
          <div className="acts">
            <Button variant="dark" iconRight="arrow-right" onClick={() => go("employers")}>Enquire now</Button>
            <Button variant="ghost-light" onClick={() => go("contact")}>Book a call</Button>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Label, SectionHead, Hero, FeatureStrip, Stars, JobCard, Carousel, ReviewCard, SectorTile, CtaBands, PhotoBand });
