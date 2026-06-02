/* Poolhall UI kit — content blocks */

function SectionHead({ eyebrow, title, lead, action, dark }) {
  return (
    <div className="section-head">
      <div className={dark ? "on-dark" : ""}>
        {eyebrow && <p className={"eyebrow" + (dark ? " on-dark" : "")}>{eyebrow}</p>}
        <h2 className={"h2" + (dark ? " on-dark" : "")}>{title}</h2>
        {lead && <p className={"lead" + (dark ? " on-dark" : "")}>{lead}</p>}
      </div>
      {action}
    </div>
  );
}

function Stars({ n = 5 }) {
  return <div className="stars">{Array.from({ length: n }).map((_, i) => <Icon key={i} name="star" />)}</div>;
}

function JobCard({ job, go }) {
  const F = window.PH_FMT;
  return (
    <article className={"jobcard" + (job.featured ? " feat" : "")} onClick={() => go("job", job)}>
      <div className="jc-top">
        <div>
          <div className="jc-sector">{job.sector}</div>
          <h3 className="jc-title">{job.title}</h3>
        </div>
        {job.featured && <span className="jc-feat"><Icon name="star" /> Featured</span>}
      </div>
      <div className="jc-meta">
        <span><Icon name="map-pin" /> {job.location}</span>
        <span><Icon name="briefcase" /> {job.type}</span>
        <span><Icon name="house" /> {job.work}</span>
        <span><Icon name="clock" /> {job.posted}</span>
      </div>
      <p className="jc-summary">{job.summary}</p>
      <div className="jc-foot">
        <div className="jc-sal">{F.salary(job)} <small>/ year</small></div>
        <span className="jc-link">View job <Icon name="arrow-right" /></span>
      </div>
    </article>
  );
}

/* Horizontal swipe carousel with prev/next */
function Carousel({ children, navId }) {
  const ref = useRef(null);
  const scroll = dir => {
    const el = ref.current;
    if (el) el.scrollBy({ left: dir * (el.clientWidth * 0.8), behavior: "smooth" });
  };
  return (
    <div className="carousel">
      <div className="carousel-track" ref={ref}>{children}</div>
      <div className="carousel-nav" style={{ marginTop: 18 }} id={navId}>
        <button className="cbtn" onClick={() => scroll(-1)} aria-label="Previous"><Icon name="arrow-left" /></button>
        <button className="cbtn" onClick={() => scroll(1)} aria-label="Next"><Icon name="arrow-right" /></button>
      </div>
    </div>
  );
}

function GoogleG({ size = 26 }) {
  return (
    <svg viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg" width={size} height={size} style={{ display: "block", flexShrink: 0 }}>
      <path fill="#4285f4" d="M533.5 278.4c0-18.5-1.5-37.1-4.7-55.3H272.1v104.8h147c-6.1 33.8-25.7 63.7-54.4 82.7v68h87.7c51.5-47.4 81.1-117.4 81.1-200.2z"/>
      <path fill="#34a853" d="M272.1 544.3c73.4 0 135.3-24.1 180.4-65.7l-87.7-68c-24.4 16.6-55.9 26-92.6 26-71 0-131.2-47.9-152.8-112.3H28.9v70.1c46.2 91.9 140.3 149.9 243.2 149.9z"/>
      <path fill="#fbbc04" d="M119.3 324.3c-11.4-33.8-11.4-70.4 0-104.2V150H28.9c-38.6 76.9-38.6 167.5 0 244.4l90.4-70.1z"/>
      <path fill="#ea4335" d="M272.1 107.7c38.8-.6 76.3 14 104.4 40.8l77.7-77.7C405 24.6 339.7-.8 272.1 0 169.2 0 75.1 58 28.9 150l90.4 70.1c21.5-64.5 81.8-112.4 152.8-112.4z"/>
    </svg>
  );
}

function ReviewCard({ r }) {
  return (
    <div className="review">
      <Stars n={r.rating} />
      <p>"{r.text}"</p>
      <div className="who">
        <span className="av">{r.name.split(" ").map(s => s[0]).join("").slice(0, 2)}</span>
        <span>
          <span className="nm">{r.name}</span><br />
          <span className="rl">{r.role}</span>
        </span>
      </div>
      <div className="review-goog-badge">
        <GoogleG size={14} />
        <span>Reviewed on Google</span>
      </div>
    </div>
  );
}

function ReviewsSlider() {
  const reviews = window.PH_DATA.reviews;
  const n = reviews.length;
  const [idx, setIdx] = useState(0);
  const [paused, setPaused] = useState(false);
  const trackRef = useRef(null);

  const scrollTo = (i) => {
    const el = trackRef.current;
    if (!el) return;
    const card = el.children[i];
    if (card) el.scrollTo({ left: card.offsetLeft - 20, behavior: "smooth" });
  };

  const go = (raw) => {
    const i = ((raw % n) + n) % n;
    setIdx(i);
    scrollTo(i);
  };

  useEffect(() => {
    if (paused) return;
    const t = setInterval(() => {
      setIdx(i => {
        const next = ((i + 1) % n + n) % n;
        scrollTo(next);
        return next;
      });
    }, 5000);
    return () => clearInterval(t);
  }, [paused, n]);

  return (
    <div className="reviews-slider" onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}>
      <div className="rgs-header">
        <div className="rgs-brand">
          <GoogleG size={28} />
          <div>
            <span className="rgs-label">Google Reviews</span>
            <div className="rgs-rating">
              <span className="rgs-score">5.0</span>
              <Stars n={5} />
              <span className="rgs-total">{n} reviews</span>
            </div>
          </div>
        </div>
        <a href="#" onClick={e => e.preventDefault()} className="jc-link">See all reviews <Icon name="arrow-right" /></a>
      </div>

      <div className="carousel">
        <div className="carousel-track" ref={trackRef}>
          {reviews.map((r, i) => <ReviewCard key={i} r={r} />)}
        </div>
      </div>

      <div className="rgs-controls">
        <button className="cbtn" onClick={() => go(idx - 1)} aria-label="Previous"><Icon name="arrow-left" /></button>
        <div className="rgs-dots">
          {reviews.map((_, i) => (
            <button key={i} className={"rgs-dot" + (i === idx ? " on" : "")} onClick={() => go(i)} aria-label={"Review " + (i + 1)} />
          ))}
        </div>
        <button className="cbtn" onClick={() => go(idx + 1)} aria-label="Next"><Icon name="arrow-right" /></button>
      </div>
    </div>
  );
}

function SectorTile({ s, go }) {
  return (
    <div className="sector-tile" onClick={() => go("jobs")}>
      <span className="sector-ic"><Icon name={s.icon} /></span>
      <span>
        <span className="st-name">{s.name}</span><br />
        <span className="st-count">{s.count} open roles</span>
      </span>
    </div>
  );
}

function SearchBar({ go }) {
  return (
    <div className="searchbar">
      <div className="field"><Icon name="search" /><input placeholder="Job title, skill or keyword" /></div>
      <div className="field"><Icon name="map-pin" /><input placeholder="Location" /></div>
      <div className="field"><Icon name="layers" />
        <select defaultValue=""><option value="">All sectors</option>
          {window.PH_DATA.sectors.map(s => <option key={s.name}>{s.name}</option>)}
        </select>
      </div>
      <Button variant="primary" size="lg" icon="search" onClick={() => go("jobs")}>Search</Button>
    </div>
  );
}

function StatStrip() {
  return (
    <div className="stats">
      {window.PH_DATA.stats.map(s => (
        <div className="stat" key={s.label}>
          <div className="v">{s.value}</div>
          <div className="l">{s.label}</div>
        </div>
      ))}
    </div>
  );
}

const HERO_IMG = window.PH_IMG.hero;
const SPLIT_IMG = window.PH_IMG.candidateCta;

Object.assign(window, { SectionHead, Stars, JobCard, Carousel, ReviewCard, ReviewsSlider, SectorTile, SearchBar, StatStrip, HERO_IMG, SPLIT_IMG });
