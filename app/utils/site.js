// Central site content, recovered from the built site.
// Edit text here and it updates across the whole app.

export const site = {
  name: 'SOFTNET Information Technology Center, Inc.',
  phones: ['(047) 461-4145', '(047) 275-0842'],
  address: '2nd Floor SFB #8, FAB, Mariveles, 2106 Bataan',
  email: 'info@softnetitc.edu.ph',
  facebook: 'SoftnetITC',
  facebookUrl: 'https://www.facebook.com/SoftnetITC',
  // Where the news is read from. The PHP backend that used to answer this is
  // retired; the news now comes from the Strapi backend, and this is its
  // address.
  //
  // This is a FALLBACK. The value the site really uses is runtimeConfig's
  // newsApiUrl (see nuxt.config.ts), which is what a build can override with
  // NUXT_NEWS_API — and that variable is now the ONLY thing that decides where
  // the news comes from, because the old PHP host must never be used again.
  //
  // It is deliberately empty rather than a guess: an empty value makes the news
  // pages say plainly that they are not configured, while a wrong one would
  // quietly show nothing — or, worse, point a live site at somebody's laptop.
  newsApiUrl: '',

  // Where THIS site is published. The news pages live here now, so this is what
  // Facebook and Google are told the address of each story is.
  //
  // Also a fallback — runtimeConfig's siteUrl is the one in use (NUXT_SITE_URL).
  siteUrl: 'https://sitci.x10.mx',
  mapUrl:
    'https://www.google.com/maps/place/SOFTNET+Information+Technology+Center/@14.4370696,120.4944206,17z/data=!3m1!4b1!4m6!3m5!1s0x33961f2adf4b0b11:0x18be54a6ca5d7404!8m2!3d14.4370696!4d120.4944206!16s%2Fg%2F1hdzyj81z?hl=en-US&entry=ttu&g_ep=EgoyMDI2MDkzMC4wIKXMDSoASAFQAw%3D%3D',
  mapEmbedUrl:
    'https://www.google.com/maps?q=SOFTNET%20Information%20Technology%20Center%2C%20Mariveles%20Bataan%2014.4370696%2C120.4944206&z=17&output=embed',
  officeHours: 'Monday to Friday, 8:00 AM - 5:00 PM',

  aboutText:
    'SOFTNET Information Technology Center is a school located at 2nd Floor SFB #8, FAB, Mariveles, Bataan. We offer Senior High School and TESDA-based programs. Visit us today — #WhereFunAndLearningConnect!',

  history: [
    'SOFTNET Information Technology Center, Inc. was conceptualized on February 14, 1997, by the school president, Mr. Gregorio M. Sison Jr., and started operations in April 1997.',
    'The college rented a space in the basement of the Philippine Economic Zone Authority (PEZA) Administration Building—then known as the Bataan Economic Zone (BEZ) and currently known as the Authority of the Freeport Area of Bataan (AFAB). The institution started with only one lecture room and a small space equipped with two computers for its laboratory. During the 1997—1998 school year, it offered a One-Year Computer Science in Programming program, drawing an initial enrollment of 87 students, along with 34 additional enrollees in October.',
    "After three years of operations, the school grew significantly, allowing it to offer additional courses for the 2000—2001 school year. These new courses, accredited by the Technical Education and Skills Development Authority (TESDA), included a Two-Year Computer Science in Programming Program, a Two-Year Computer Technician Program, and a One-Year General Course. Continuous student enrollment growth prompted the management to acquire additional rooms to accommodate the students' needs.",
    'In 2009—2010 school year, responding to the demand for technology and TESDA-based courses, the school introduced Computer Programming NC IV, Computer Hardware Servicing NC II, and Hotel and Restaurant Services. From June 2009 to December 2013, these additional courses were held separately at the A&F Building in Ricarte Street in Mariveles, Bataan.',
    'To accommodate the growing student population, management moved the institution to a larger space, which is currently located at the 2nd Floor of SFB No. 8, FAB, Mariveles, Bataan.',
    'In 2010, the newly elected President of the Republic of the Philippines, Benigno Simeon C. Aquino III, proposed the K to 12 Basic Education Program, which was enacted into law in 2013. Following its nationwide implementation in the school year 2016—2017, SOFTNET management applied to offer Senior High School Programs. The Department of Education (DepEd) approved its Technical-Vocational-Livelihood (TVL) Track, featuring Information and Communications Technology (ICT) and Home Economics strands, which initially drew 123 Grade 11 students. By the 2017—2018 school year, SOFTNET expanded its offerings to include the Academic Track, featuring the Accountancy, Business, and Management (ABM) and Humanities and Social Sciences (HUMSS) strands.',
    'For its TESDA-based programs in the 2019—2020 school year, SOFTNET offered higher qualifications under the TESDA curriculum, introducing various Diploma Programs with corresponding NC II to NC IV certifications awarded per semester. This began with Three-Year Diploma Courses in Hotel and Restaurant Management Technology, Tourism and Travel Management Technology, Information Technology, and Computer Engineering Technology.',
  ],

  mission:
    'To develop and provide high quality academic and technical skills programs and that would ensure employability and productivity of young men and women through proper values and principles as a means to achieve success with excellence for a better quality of life.',

  vision:
    'A center of excellence in academic and technical skills programs in pursuing dynamic opportunities parallel to global standards, thereby, uplifting the socio-economic growth of the country.',

  hymn: [
    {
      chorus: false,
      lines: [
        "You're a torch of technology",
        'Sparks of progress and unity',
        'Creating a humanity',
        'For the brightest and the witty',
      ],
    },
    {
      chorus: false,
      lines: [
        'We will fly amongst the rest',
        "And we'll be amongst the best",
        'As someday we shall come to see',
        'Fruit of labor instilled in me',
      ],
    },
    {
      chorus: true,
      lines: [
        'SOFTNET, dearest Alma mater',
        "Your wisdom we'll always bear",
        "Like winds upon the eagle's wings",
        'Strength and courage we shall bring',
      ],
    },
    {
      chorus: false,
      lines: [
        'You lead with a caring heart',
        "Giving and sharing all you've got",
        'Mind and soul knowledge and love',
        "Head on we'll soar round the globe",
      ],
    },
    {
      chorus: false,
      lines: [
        'We will fly amongst the rest',
        "And we'll be amongst the best",
        'As someday we shall come to see',
        'Our brightest future yet to be',
      ],
    },
    {
      chorus: true,
      lines: [
        'SOFTNET, dearest Alma mater',
        "Your wisdom we'll always bear",
        "Like winds upon an eagle's wings",
        'Faith and knowledge',
        'We shall bring.',
      ],
    },
  ],

  academicTrack: {
    title: 'Academic Track',
    description:
      'A track designed for college-bound learners, offering elective clusters in STEM, Business and Entrepreneurship, Humanities, Sports, and Field Experience.',
    electives: ['Business and Entrepreneurship', 'Arts, Social Science, and Humanities'],
  },

  techProTrack: {
    title: 'Technical-Professional (TechPro) Track',
    description:
      'A track focused on industry-ready learners, offering elective clusters in ICT, Hospitality and Tourism, Food Innovation, and Creative Arts and Design.',
    electives: [
      'Hospitality and Tourism',
      'Creative Arts and Design',
      'ICT Support',
      'Construction and Building Technology',
    ],
  },

  diplomaPrograms: [
    {
      title: 'Information Technology',
      desc: 'A three-year hands-on program covering programming, web and mobile development, networking, databases, and cybersecurity.',
      iconKey: 'laptop',
      icon: 'computer',
      duration: '3-Year Diploma Program',
    },
    {
      title: 'Hospitality Technology',
      desc: 'A three-year hands-on program covering culinary arts, food and beverage service, housekeeping, front office operations, and restaurant management.',
      iconKey: 'utensils',
      icon: 'chef',
      duration: '3-Year Diploma Program',
    },
    {
      title: 'Computer Engineering Technology',
      desc: 'A three-year hands-on program covering computer hardware, embedded systems, networking, robotics, and electronics.',
      iconKey: 'microchip',
      icon: 'microchip',
      duration: '3-Year Diploma Program',
    },
    {
      title: 'Travel and Tourism Technology',
      desc: 'A three-year hands-on program covering tour operations, travel booking, hospitality service, tourism marketing, and customer relations.',
      iconKey: 'plane',
      icon: 'airplane',
      duration: '3-Year Diploma Program',
    },
  ],

  facilities: Array.from({ length: 15 }, (_, i) => ({
    img: `/_sitc/images/facilities/${i + 1}.webp`,
    alt: `SOFTNET facility photo ${i + 1}`,
  })).filter((_, i) => i !== 10),

}

// The home page shows all diploma programs.
export function homePrograms() {
  return site.diplomaPrograms
}

// Truncate helper used for the home page course summaries.
export function truncate(text, max) {
  const value = String(text ?? '')
  return value.length <= max ? value : value.slice(0, max).trimEnd() + '...'
}
