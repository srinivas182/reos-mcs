/** Tests run on Texas time, whatever the machine's zone, so times read the same everywhere. */
module.exports = () => {
  process.env.TZ = 'America/Chicago';
};
