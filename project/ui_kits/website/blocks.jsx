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

Object.assign(window, { SectionHead, Stars, JobCard, Carousel, ReviewCard, SectorTile, SearchBar, StatStrip, HERO_IMG, SPLIT_IMG });
