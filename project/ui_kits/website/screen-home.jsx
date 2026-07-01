/* Poolhall UI kit v2 — Home (§9.1) */

function Home({ go }) {
  const D = window.PH_DATA;
  const jobs = D.jobs.slice().sort((a, b) => (b.featured ? 1 : 0) - (a.featured ? 1 : 0));
  return (
    <main>
      <Hero go={go} />
      <FeatureStrip />

      {/* featured jobs */}
      <section className="section">
        <div className="container">
          <SectionHead idx="01 /" label="Featured roles" title="Live jobs, this week"
            lead="A snapshot of what we're recruiting right now. New roles land all the time."
            action={<Button variant="ghost" iconRight="arrow-right" onClick={() => go("jobs")}>View all jobs</Button>} />
          <Carousel>
            {jobs.map(j => <JobCard key={j.id} job={j} go={go} />)}
          </Carousel>
        </div>
      </section>

      {/* google reviews — dark photo band */}
      <section className="section cta-photo" style={{ padding: 0 }}>
        <img src={window.PH_IMG.ctaPhoto} alt="" onError={e => e.target.style.display='none'} />
        <div className="container" style={{ position: "relative", zIndex: 2, padding: "96px 32px" }}>
          <SectionHead dark idx="02 /" label="Google reviews" title="Rated five stars by the people we work with"
            lead="Real feedback from the people we've placed and the businesses we've helped to hire."
            action={<a href="#" className="btn-text" style={{ color: "#fff" }} onClick={e => e.preventDefault()}>Read all reviews <Icon name="arrow-right" /></a>} />
          <Carousel light>
            {D.reviews.map((r, i) => <ReviewCard key={i} r={r} glass />)}
          </Carousel>
        </div>
      </section>

      {/* sector & service snapshot */}
      <section className="section bg-alt">
        <div className="container">
          <SectionHead idx="03 /" label="Where we work" title="Three sectors, real depth"
            lead="We focus on a few industries rather than all of them, so we really understand the work, the people who build, make and market British business." />
          <div className="sector-grid">
            {D.sectors.map(s => <SectorTile key={s.name} s={s} go={go} />)}
          </div>
        </div>
      </section>

      {/* what makes Poolhall different */}
      <section className="section">
        <div className="container">
          <div className="split">
            <div>
              <Label idx="04 /">What makes us different</Label>
              <h2 className="h2">Recruitment with people at the heart of it.</h2>
              <p className="lead" style={{ marginTop: 16 }}>We're an independent agency that believes recruitment works best when it's done thoughtfully, honestly, and with people at the centre.</p>
              <ul className="checklist">
                <li><Icon name="check-circle" /> Independent and experienced, you'll always speak with a senior consultant who knows your sector.</li>
                <li><Icon name="check-circle" /> Friendly, honest advice, we'll always share our genuine view to help you decide.</li>
                <li><Icon name="check-circle" /> Quality over quantity, a considered shortlist that's thoughtfully put together.</li>
                <li><Icon name="check-circle" /> Easy to reach, a local team that's always happy to pick up the phone.</li>
              </ul>
            </div>
            <div className="media-collage">
              <div className="m tall"><img src={window.PH_IMG.aboutA} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.aboutB} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.aboutC} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
            </div>
          </div>
        </div>
      </section>

      {/* stat row */}
      <section className="section tight bg-dark">
        <div className="container">
          <div className="stats">
            {D.stats.map(s => (
              <div className="stat" key={s.label}>
                <div className="v">{s.value}<em>{s.suffix}</em></div>
                <div className="l">{s.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* paired CTA bands */}
      <CtaBands go={go} />
    </main>
  );
}

window.Home = Home;
