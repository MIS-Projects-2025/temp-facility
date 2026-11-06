// Generic nested update function in JS
export default function updateNested(obj, path, value) {
  if (!path) return obj; // empty path, return original

  const keys = path.split('.');
  const newObj = Array.isArray(obj) ? [...obj] : { ...obj };
  let temp = newObj;

  for (let i = 0; i < keys.length - 1; i++) {
    const key = keys[i];

    // If key doesn't exist or is not an object/array, create object
    if (temp[key] === undefined || temp[key] === null || typeof temp[key] !== 'object') {
      temp[key] = {};
    } else {
      temp[key] = Array.isArray(temp[key]) ? [...temp[key]] : { ...temp[key] };
    }

    temp = temp[key];
  }

  temp[keys[keys.length - 1]] = value;
  return newObj;
}